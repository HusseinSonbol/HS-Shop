import 'bootstrap';

const initializeProductSharing = () => {
    document.querySelectorAll('[data-product-share]').forEach((share) => {
        if (share.dataset.initialized === 'true') {
            return;
        }

        share.dataset.initialized = 'true';
        const title = share.dataset.shareTitle ?? document.title;
        const url = share.dataset.shareUrl ?? window.location.href;
        const status = share.querySelector('[data-share-status]');

        const showStatus = (message) => {
            if (!status) {
                return;
            }

            status.textContent = message;
            status.hidden = false;
        };

        const copyLink = async () => {
            try {
                if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(url);
                } else {
                    const temporaryInput = document.createElement('textarea');
                    temporaryInput.value = url;
                    temporaryInput.setAttribute('readonly', '');
                    temporaryInput.style.position = 'fixed';
                    temporaryInput.style.opacity = '0';
                    document.body.append(temporaryInput);
                    temporaryInput.select();
                    const copied = document.execCommand('copy');
                    temporaryInput.remove();
                    if (!copied) {
                        throw new Error('Clipboard copy was not available.');
                    }
                }

                showStatus(share.dataset.copySuccess ?? '');
            } catch {
                showStatus(share.dataset.copyFailure ?? '');
            }
        };

        share.querySelector('[data-share-copy]')?.addEventListener('click', copyLink);
        share.querySelector('[data-share-native]')?.addEventListener('click', async () => {
            if (!navigator.share) {
                await copyLink();
                return;
            }

            try {
                await navigator.share({ title, url });
            } catch (error) {
                if (error?.name !== 'AbortError') {
                    showStatus(share.dataset.copyFailure ?? '');
                }
            }
        });
    });
};

const initializeMobileCategoryTree = () => {
    document.querySelectorAll('[data-mobile-category-toggle]').forEach((button) => {
        if (button.dataset.initialized === 'true') {
            return;
        }

        button.dataset.initialized = 'true';
        button.addEventListener('click', () => {
            const list = document.getElementById(button.getAttribute('aria-controls'));
            if (!list) {
                return;
            }

            const isExpanded = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', String(!isExpanded));
            button.setAttribute('aria-label', isExpanded ? button.dataset.expandLabel : button.dataset.collapseLabel);
            list.hidden = isExpanded;

            if (isExpanded) {
                list.querySelectorAll('[data-mobile-category-toggle]').forEach((nestedButton) => {
                    nestedButton.setAttribute('aria-expanded', 'false');
                    nestedButton.setAttribute('aria-label', nestedButton.dataset.expandLabel);
                    const nestedList = document.getElementById(nestedButton.getAttribute('aria-controls'));
                    if (nestedList) {
                        nestedList.hidden = true;
                    }
                });
            }
        });
    });
};

const initializeDesktopCategoryMenu = () => {
    document.querySelectorAll('.hs-desktop-category-nav').forEach((item) => {
        const button = item.querySelector(':scope > [data-bs-toggle="dropdown"]');
        if (!button || item.dataset.initialized === 'true') {
            return;
        }

        item.dataset.initialized = 'true';
        const updateExpanded = (expanded) => button.setAttribute('aria-expanded', String(expanded));
        const remainsOpenByClick = () => item.querySelector(':scope > .dropdown-menu')?.classList.contains('show');

        item.addEventListener('pointerenter', () => updateExpanded(true));
        item.addEventListener('pointerleave', () => updateExpanded(Boolean(remainsOpenByClick())));
        item.addEventListener('focusin', () => updateExpanded(true));
        item.addEventListener('focusout', (event) => {
            if (!item.contains(event.relatedTarget)) {
                updateExpanded(Boolean(remainsOpenByClick()));
            }
        });
    });
};

const initializeProductDetails = () => {
    document.querySelectorAll('[data-product-detail]').forEach((detail) => {
        if (detail.dataset.initialized === 'true') {
            return;
        }

        const variantsElement = detail.querySelector('[data-product-variants]');
        const selectedVariantInput = detail.querySelector('[data-selected-variant]');
        if (!variantsElement || !selectedVariantInput) {
            return;
        }

        let variants;
        try {
            variants = JSON.parse(variantsElement.textContent);
        } catch {
            return;
        }

        if (!Array.isArray(variants) || variants.length === 0) {
            return;
        }

        detail.dataset.initialized = 'true';
        const attributeGroups = Array.from(detail.querySelectorAll('[data-attribute-group]'));
        const directVariantSelect = detail.querySelector('[data-direct-variant]');
        const salePriceElement = detail.querySelector('[data-sale-price]');
        const salePriceValue = detail.querySelector('[data-price-value]');
        const regularPriceElement = detail.querySelector('[data-regular-price]');
        const saleBadge = detail.querySelector('[data-product-sale-badge]');
        const variantSku = detail.querySelector('[data-variant-sku]');
        const mainImage = detail.querySelector('[data-main-product-image]');
        const thumbnails = Array.from(detail.querySelectorAll('[data-gallery-thumb]'));
        const selectedValues = {};

        const selectedVariant = variants.find((variant) => String(variant.id) === selectedVariantInput.value) ?? variants[0];
        Object.assign(selectedValues, selectedVariant.attributes);

        const updateOptions = () => {
            attributeGroups.forEach((group) => {
                const attributeId = group.dataset.attributeGroup;
                group.querySelectorAll('[data-value-id]').forEach((button) => {
                    const valueId = button.dataset.valueId;
                    const available = variants.some((variant) => {
                        if (String(variant.attributes[attributeId]) !== valueId) {
                            return false;
                        }

                        return Object.entries(selectedValues).every(([selectedAttributeId, selectedValueId]) => (
                            selectedAttributeId === attributeId
                            || String(variant.attributes[selectedAttributeId]) === String(selectedValueId)
                        ));
                    });
                    const isSelected = String(selectedValues[attributeId]) === valueId;
                    button.disabled = !available;
                    button.classList.toggle('is-selected', isSelected);
                    button.setAttribute('aria-pressed', String(isSelected));
                });
            });
        };

        const applyVariant = (variant) => {
            if (!variant) {
                return;
            }

            selectedVariantInput.value = variant.id;
            Object.keys(selectedValues).forEach((attributeId) => delete selectedValues[attributeId]);
            Object.assign(selectedValues, variant.attributes);
            if (directVariantSelect) {
                directVariantSelect.value = String(variant.id);
            }

            const hasSale = variant.salePrice !== null
                && Number(variant.salePrice) < Number(variant.regularPrice);
            if (salePriceValue) {
                salePriceValue.textContent = hasSale ? variant.displaySalePrice : variant.displayRegularPrice;
            }
            if (salePriceElement) {
                salePriceElement.dataset.onSale = String(hasSale);
            }
            if (regularPriceElement) {
                regularPriceElement.textContent = hasSale
                    ? `${variant.displayRegularPrice} ${regularPriceElement.dataset.currencyLabel ?? ''}`
                    : '';
                regularPriceElement.hidden = !hasSale;
            }
            if (saleBadge) {
                saleBadge.hidden = !hasSale;
            }
            if (variantSku) {
                variantSku.textContent = variant.sku;
            }
            if (mainImage && variant.images?.length) {
                mainImage.src = variant.images[0];
                mainImage.alt = mainImage.dataset.productName ?? '';
                thumbnails.forEach((thumbnail) => {
                    thumbnail.classList.remove('is-active');
                    thumbnail.setAttribute('aria-pressed', 'false');
                });
            }

            updateOptions();
        };

        attributeGroups.forEach((group) => {
            group.querySelectorAll('[data-value-id]').forEach((button) => {
                button.addEventListener('click', () => {
                    const attributeId = group.dataset.attributeGroup;
                    selectedValues[attributeId] = button.dataset.valueId;

                    let match = variants.find((variant) => Object.entries(selectedValues).every(([id, value]) => (
                        String(variant.attributes[id]) === String(value)
                    )));

                    if (!match) {
                        match = variants.find((variant) => String(variant.attributes[attributeId]) === button.dataset.valueId);
                    }

                    applyVariant(match);
                });
            });
        });

        directVariantSelect?.addEventListener('change', () => {
            const match = variants.find((variant) => String(variant.id) === directVariantSelect.value);
            applyVariant(match);
        });

        thumbnails.forEach((thumbnail) => {
            thumbnail.addEventListener('click', () => {
                if (!mainImage) {
                    return;
                }

                mainImage.src = thumbnail.dataset.imageUrl;
                mainImage.alt = thumbnail.dataset.imageAlt;
                thumbnails.forEach((item) => {
                    const isActive = item === thumbnail;
                    item.classList.toggle('is-active', isActive);
                    item.setAttribute('aria-pressed', String(isActive));
                });
            });
        });

        updateOptions();
    });
};

const initializeProductCarousels = () => {
    document.querySelectorAll('[data-product-carousel]').forEach((carousel) => {
        if (carousel.dataset.initialized === 'true') {
            return;
        }

        const viewport = carousel.querySelector('[data-carousel-viewport]');
        const track = carousel.querySelector('[data-carousel-track]');
        const section = carousel.closest('.hs-product-section');
        const controls = section?.querySelector('[data-carousel-controls]');
        const previousButton = controls?.querySelector('[data-carousel-prev]');
        const nextButton = controls?.querySelector('[data-carousel-next]');
        const slides = Array.from(track?.children ?? []);

        if (!viewport || !track || slides.length < 2 || !controls || !previousButton || !nextButton) {
            return;
        }

        carousel.dataset.initialized = 'true';
        const interval = Number.parseInt(carousel.dataset.interval ?? '7000', 10);
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let index = 0;
        let baseIndex = 0;
        let visibleSlides = 0;
        let step = 0;
        let isLooping = false;
        let isAnimating = false;
        let timer;
        let paused = false;

        const render = () => {
            const direction = track.dir === 'rtl' ? 1 : -1;
            track.style.transform = `translate3d(${direction * index * step}px, 0, 0)`;
        };

        const markClone = (slide) => {
            const clone = slide.cloneNode(true);
            clone.dataset.carouselClone = 'true';
            clone.setAttribute('aria-hidden', 'true');
            clone.querySelectorAll('a, button, input, select, textarea, [tabindex]').forEach((element) => {
                element.setAttribute('tabindex', '-1');
            });
            return clone;
        };

        const rebuildLoop = () => {
            track.querySelectorAll('[data-carousel-clone]').forEach((clone) => clone.remove());
            isLooping = slides.length > visibleSlides;
            controls.hidden = !isLooping;
            baseIndex = 0;
            index = 0;

            if (isLooping) {
                const before = slides.slice(-visibleSlides).map(markClone);
                const after = slides.slice(0, visibleSlides).map(markClone);
                track.prepend(...before);
                track.append(...after);
                baseIndex = visibleSlides;
                index = baseIndex;
            }
        };

        const update = () => {
            const slideWidth = slides[0].getBoundingClientRect().width;
            const gap = Number.parseFloat(window.getComputedStyle(track).columnGap) || 0;
            step = slideWidth + gap;
            const nextVisibleSlides = Math.max(1, Math.floor((viewport.clientWidth + gap) / step));

            if (nextVisibleSlides !== visibleSlides) {
                visibleSlides = nextVisibleSlides;
                rebuildLoop();
            }

            isAnimating = false;
            previousButton.disabled = false;
            nextButton.disabled = false;
            render();
        };

        const stopAutoplay = () => {
            window.clearInterval(timer);
            timer = undefined;
        };

        const startAutoplay = () => {
            stopAutoplay();
            if (reducedMotion || paused || !isLooping || document.hidden) {
                return;
            }

            timer = window.setInterval(() => {
                moveNext();
            }, Number.isFinite(interval) && interval > 0 ? interval : 7000);
        };

        const normalizeLoopPosition = () => {
            if (index === baseIndex + slides.length) {
                index = baseIndex;
            } else if (index === baseIndex - 1) {
                index = baseIndex + slides.length - 1;
            } else {
                return;
            }

            track.style.transition = 'none';
            render();
            track.offsetHeight;
            track.style.removeProperty('transition');
        };

        const animateTo = (nextIndex) => {
            if (!isLooping || isAnimating) {
                return;
            }

            if (reducedMotion) {
                index = nextIndex;
                render();
                normalizeLoopPosition();
                return;
            }

            isAnimating = true;
            previousButton.disabled = true;
            nextButton.disabled = true;
            index = nextIndex;
            render();
        };

        const moveNext = () => {
            const lastOriginalIndex = baseIndex + slides.length - 1;
            animateTo(index >= lastOriginalIndex ? baseIndex + slides.length : index + 1);
        };

        const movePrevious = () => {
            animateTo(index <= baseIndex ? baseIndex - 1 : index - 1);
        };

        track.addEventListener('transitionend', (event) => {
            if (event.target !== track || event.propertyName !== 'transform') {
                return;
            }

            normalizeLoopPosition();
            isAnimating = false;
            previousButton.disabled = false;
            nextButton.disabled = false;
        });

        previousButton.addEventListener('click', () => {
            movePrevious();
            startAutoplay();
        });

        nextButton.addEventListener('click', () => {
            moveNext();
            startAutoplay();
        });

        carousel.addEventListener('mouseenter', () => {
            paused = true;
            stopAutoplay();
        });
        carousel.addEventListener('mouseleave', () => {
            paused = false;
            startAutoplay();
        });
        carousel.addEventListener('focusin', () => {
            paused = true;
            stopAutoplay();
        });
        carousel.addEventListener('focusout', (event) => {
            if (!carousel.contains(event.relatedTarget)) {
                paused = false;
                startAutoplay();
            }
        });

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stopAutoplay();
            } else {
                startAutoplay();
            }
        });

        update();
        startAutoplay();
        window.addEventListener('resize', () => {
            update();
            startAutoplay();
        });
    });
};

const fitTestimonialCarousels = () => {
    document.querySelectorAll('.hs-testimonial-carousel').forEach((carousel) => {
        const inner = carousel.querySelector('.carousel-inner');
        const slides = Array.from(carousel.querySelectorAll('.carousel-item'));

        if (!inner || slides.length < 2 || inner.clientWidth === 0) {
            return;
        }

        let tallestSlide = 0;
        slides.forEach((slide) => {
            const originalStyle = slide.getAttribute('style');
            slide.style.setProperty('display', 'block', 'important');
            slide.style.setProperty('position', 'absolute', 'important');
            slide.style.setProperty('top', '0', 'important');
            slide.style.setProperty('left', '0', 'important');
            slide.style.setProperty('float', 'none', 'important');
            slide.style.setProperty('width', `${inner.clientWidth}px`, 'important');
            slide.style.setProperty('margin', '0', 'important');
            slide.style.setProperty('visibility', 'hidden', 'important');
            slide.style.setProperty('transform', 'none', 'important');
            tallestSlide = Math.max(tallestSlide, Math.ceil(slide.getBoundingClientRect().height));

            if (originalStyle === null) {
                slide.removeAttribute('style');
            } else {
                slide.setAttribute('style', originalStyle);
            }
        });

        inner.style.height = `${tallestSlide}px`;
    });
};

let testimonialResizeFrame;
const scheduleTestimonialResize = () => {
    window.cancelAnimationFrame(testimonialResizeFrame);
    testimonialResizeFrame = window.requestAnimationFrame(fitTestimonialCarousels);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        fitTestimonialCarousels();
        initializeDesktopCategoryMenu();
        initializeMobileCategoryTree();
        initializeProductSharing();
        initializeProductDetails();
        initializeProductCarousels();
    }, { once: true });
} else {
    fitTestimonialCarousels();
    initializeDesktopCategoryMenu();
    initializeMobileCategoryTree();
    initializeProductSharing();
    initializeProductDetails();
    initializeProductCarousels();
}

window.addEventListener('load', fitTestimonialCarousels, { once: true });
window.addEventListener('resize', scheduleTestimonialResize);
document.fonts?.ready.then(fitTestimonialCarousels);
