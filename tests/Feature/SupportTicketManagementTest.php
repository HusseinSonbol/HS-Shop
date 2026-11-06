<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupportTicketManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_message_can_be_converted_to_a_ticket_once_and_notifies_staff(): void
    {
        $staff = $this->createStaff(['dashboard.access', 'contact_messages.manage', 'support_tickets.manage']);
        $message = ContactMessage::query()->create([
            'sender_name' => 'Guest sender', 'email' => 'guest@example.test', 'subject' => 'Question', 'message' => 'Please help.',
            'status' => 'new', 'received_at' => now(),
        ]);

        $this->actingAs($staff)->post(route('admin.support-tickets.convert-contact', $message), ['priority' => 'high'])
            ->assertRedirect();
        $message->refresh();
        $this->assertSame('converted', $message->status);
        $this->assertNotNull($message->converted_ticket_id);
        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $message->converted_ticket_id,
            'body' => 'Please help.', 'visibility' => 'public', 'sender_type' => 'customer',
        ]);
        $this->assertDatabaseHas('support_ticket_events', ['support_ticket_id' => $message->converted_ticket_id, 'event_type' => 'converted_from_contact']);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $staff->id]);

        $this->post(route('admin.support-tickets.convert-contact', $message), [])->assertRedirect(route('admin.support-tickets.show', $message->converted_ticket_id));
        $this->assertDatabaseCount('support_tickets', 1);
    }

    public function test_customer_can_open_ticket_and_only_sees_public_messages_and_attachments(): void
    {
        Storage::fake('local');
        $customerUser = User::factory()->create(['is_active' => true]);
        $customer = Customer::query()->create([
            'user_id' => $customerUser->id, 'customer_number' => 'CUS-TICKET-1', 'display_name' => 'Ticket Customer',
            'phone' => '01000000001', 'email' => $customerUser->email,
        ]);
        $staff = $this->createStaff(['dashboard.access', 'support_tickets.manage']);

        $this->actingAs($customerUser, 'customer')->post(route('store.account.tickets.store'), [
            'subject' => 'Order question', 'body' => 'Where is my order?',
            'attachments' => [UploadedFile::fake()->create('question.pdf', 30, 'application/pdf')],
        ])->assertRedirect();

        $ticketId = (int) DB::table('support_tickets')->value('id');
        $this->assertDatabaseHas('support_ticket_attachments', ['support_ticket_message_id' => DB::table('support_ticket_messages')->value('id')]);
        $storedPath = DB::table('support_ticket_attachments')->value('storage_path');
        Storage::disk('local')->assertExists($storedPath);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $staff->id]);

        $this->actingAs($staff)->post(route('admin.support-tickets.messages.store', $ticketId), [
            'body' => 'Staff only note.', 'visibility' => 'internal',
            'attachments' => [UploadedFile::fake()->create('private.pdf', 25, 'application/pdf')],
        ])->assertRedirect();
        $privateAttachment = DB::table('support_ticket_attachments')->orderByDesc('id')->first();

        $this->actingAs($customerUser, 'customer')->get(route('store.account.tickets.show', $ticketId))
            ->assertOk()->assertSee('Where is my order?')->assertDontSee('Staff only note.')->assertDontSee('private.pdf');
        $this->get(route('store.account.tickets.attachments.show', [$ticketId, $privateAttachment->id]))->assertNotFound();

        $otherUser = User::factory()->create(['is_active' => true]);
        $otherCustomer = Customer::query()->create([
            'user_id' => $otherUser->id, 'customer_number' => 'CUS-TICKET-2', 'display_name' => 'Other Customer',
            'phone' => '01000000002', 'email' => $otherUser->email,
        ]);
        $this->assertNotSame($customer->id, $otherCustomer->id);
        $this->actingAs($otherUser, 'customer')->get(route('store.account.tickets.show', $ticketId))->assertNotFound();
    }

    public function test_ticket_routes_require_staff_permission_and_status_changes_are_audited(): void
    {
        $noPermission = $this->createStaff(['dashboard.access']);
        $this->actingAs($noPermission)->get(route('admin.support-tickets.index'))->assertForbidden();

        $customerUser = User::factory()->create(['is_active' => true]);
        $customer = Customer::query()->create([
            'user_id' => $customerUser->id, 'customer_number' => 'CUS-TICKET-3', 'display_name' => 'Status Customer',
            'phone' => '01000000003', 'email' => $customerUser->email,
        ]);
        $ticketId = DB::table('support_tickets')->insertGetId([
            'ticket_number' => 'TKT-STATUS-1', 'customer_id' => $customer->id, 'subject' => 'Status test', 'status' => 'open',
            'priority' => 'normal', 'requester_name' => 'Status Customer', 'requester_email' => $customerUser->email,
            'opened_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $staff = $this->createStaff(['dashboard.access', 'support_tickets.manage']);
        $this->actingAs($staff)->patch(route('admin.support-tickets.status', $ticketId), ['status' => 'waiting_customer', 'note' => 'Need order details.'])
            ->assertRedirect();
        $this->assertDatabaseHas('support_ticket_events', [
            'support_ticket_id' => $ticketId, 'actor_user_id' => $staff->id,
            'event_type' => 'status_changed', 'previous_value' => 'open', 'new_value' => 'waiting_customer',
        ]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $customerUser->id]);
        $this->patch(route('admin.support-tickets.assignment', $ticketId), ['assigned_to' => $staff->id])->assertRedirect();
        $staffNotificationId = DB::table('notifications')->where('notifiable_id', $staff->id)->value('id');
        $this->get(route('admin.notifications.index'))->assertOk()->assertSee(__('admin.notifications.title'));
        $this->post(route('admin.notifications.read', $staffNotificationId))->assertRedirect();
        $this->assertNotNull(DB::table('notifications')->where('id', $staffNotificationId)->value('read_at'));
    }

    /** @param list<string> $permissions */
    private function createStaff(array $permissions): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $roleCode = 'support-test-'.substr(md5(implode('-', $permissions).$user->id), 0, 12);
        DB::table('roles')->insert(['code' => $roleCode, 'name' => 'Support test staff', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('user_role')->insert(['user_id' => $user->id, 'role_id' => DB::table('roles')->where('code', $roleCode)->value('id'), 'assigned_by' => null, 'created_at' => now()]);
        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore(['code' => $permission, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('role_permission')->insertOrIgnore([
                'role_id' => DB::table('roles')->where('code', $roleCode)->value('id'),
                'permission_id' => DB::table('permissions')->where('code', $permission)->value('id'),
            ]);
        }

        return $user;
    }
}
