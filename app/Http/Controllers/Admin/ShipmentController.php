<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShipmentEventRequest;
use App\Http\Requests\Admin\ShipmentRequest;
use App\Services\Admin\ShipmentManagementService;
use Illuminate\Http\RedirectResponse;

class ShipmentController extends Controller
{
    public function store(ShipmentRequest $request, int $order, ShipmentManagementService $shipments): RedirectResponse
    {
        $created = $shipments->create($order, $request->user(), $request->validated());

        return redirect()->route('admin.orders.show', $order)->with('status', __($created ? 'admin.shipments.created' : 'admin.shipments.already_created'));
    }

    public function storeEvent(ShipmentEventRequest $request, int $order, int $shipment, ShipmentManagementService $shipments): RedirectResponse
    {
        $created = $shipments->recordEvent($order, $shipment, $request->user(), $request->validated());

        return redirect()->route('admin.orders.show', $order)->with('status', __($created ? 'admin.shipments.event_saved' : 'admin.shipments.event_already_saved'));
    }
}
