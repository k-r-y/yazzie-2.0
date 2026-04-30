<?php
/**
 * _booking_stepper.php — Shared 4-Step Booking Modal
 * Include in any view that needs the new booking flow.
 *
 * Variables the including view must define BEFORE including:
 *   $bookingStepperRole = 'admin' | 'frontdesk'   (controls visible fields)
 *
 * The modal ID is: bookingStepperModal
 * Open it: Modal.open('bookingStepperModal') or openBookingStepper()
 */
$stepperRole = $bookingStepperRole ?? 'admin';
$dynamicTerms = appSetting('terms_and_conditions', "Full payment is required on or before the event date.");
$dynamicPrivacy = appSetting('data_privacy_notice', "We value your privacy. Your personal data is handled securely.");
?>

<!-- ╔══════════════════════════════════════════════════════════════╗
     ║   4-STEP BOOKING STEPPER MODAL                             ║
     ╚══════════════════════════════════════════════════════════════╝ -->
<div class="modal fade" id="bookingStepperModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content" style="border-radius:24px; overflow:hidden;">

            <!-- ── HEADER ── -->
            <div class="modal-header" style="padding:18px 24px; border-bottom:0.5px solid rgba(60,60,67,0.08); position:relative;">
                <div style="padding-right:40px;">
                    <h5 class="modal-title" style="font-size:16px; font-weight:800; letter-spacing:-0.3px;">
                        <i class="fas fa-calendar-plus me-2" style="color:var(--sys-green);"></i>New Event Booking
                    </h5>
                    <div id="stepperSubtitle" style="font-size:11px; color:rgba(60,60,67,0.4); margin-top:1px;">Step 1 of 4</div>
                </div>
                <button type="button" class="btn-close" onclick="closeBookingStepper()" style="position:absolute; right:20px; top:20px;"></button>
            </div>

            <!-- ── STEP PROGRESS NAV ── -->
            <div style="padding:20px 28px 0; background:rgba(242,242,247,0.5); border-bottom:0.5px solid rgba(60,60,67,0.08);">
                <div id="stepNav" style="display:flex; align-items:center; gap:0; margin-bottom:0;">

                    <?php
                    $steps = [
                        ['icon'=>'fa-calendar-day',  'label'=>'Date'],
                        ['icon'=>'fa-user',          'label'=>'Client'],
                        ['icon'=>'fa-bowl-food',     'label'=>'Package'],
                        ['icon'=>'fa-file-invoice',  'label'=>'Summary'],
                    ];
                    foreach ($steps as $i => $s):
                        $n = $i + 1;
                    ?>
                    <div class="stepper-step <?= $n === 1 ? 'active' : '' ?>" id="stepNav<?= $n ?>"
                         style="display:flex; flex-direction:column; align-items:center; gap:6px; flex:1; padding-bottom:14px;">
                        <div class="stepper-circle" style="
                            width:34px; height:34px; border-radius:50%; font-size:14px;
                            display:flex; align-items:center; justify-content:center; font-weight:700;
                            transition:all 0.25s ease; position:relative; z-index:2;
                        "><?= $n ?></div>
                        <div style="font-size:10px; font-weight:600; letter-spacing:0.3px; text-transform:uppercase; transition:color 0.2s;"><?= $s['label'] ?></div>
                    </div>
                    <?php if ($n < 4): ?>
                    <div class="stepper-line" id="stepLine<?= $n ?>" style="flex:1; height:1.5px; background:rgba(60,60,67,0.12); margin-bottom:26px; transition:background 0.3s; max-width:55px;"></div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── STEP PANELS ── -->
            <div class="modal-body" style="padding:28px; min-height:380px;">

                <!-- ══ STEP 1: Date & Availability ══ -->
                <div class="stepper-panel active" id="panel1">
                    <div style="max-width:560px; margin:0 auto;">
                        <h6 style="font-size:15px; font-weight:700; margin-bottom:4px;">📅 When is the event?</h6>
                        <p style="font-size:13px; color:rgba(60,60,67,0.5); margin-bottom:22px;">Choose a date — we'll instantly check if it's available.</p>

                        <div class="form-grid-2" style="gap:14px;">
                            <div class="form-group">
                                <label class="form-label" for="s1_date">Event Date <span class="required">*</span></label>
                                <input type="date" class="form-control" id="s1_date"
                                       min="<?= date('Y-m-d', strtotime('+' . MIN_LEAD_TIME_DAYS . ' days')) ?>"
                                       max="<?= date('Y-m-d', strtotime('+1 year')) ?>"
                                       title="Select the scheduled date for the event">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="s1_time">Event Time <span class="required">*</span></label>
                                <input type="time" class="form-control" id="s1_time" 
                                       onchange="onTimeChange(this.value)" 
                                       oninput="onTimeChange(this.value)"
                                       onblur="onTimeChange(this.value)"
                                       title="Select the start time of the catering service">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="s1_type">Event Type <span class="required">*</span></label>
                            <select class="form-control" id="s1_type" onchange="onEventTypeChange(this.value)" title="Choose the nature of the celebration">
                                <option value="Wedding" selected>Wedding</option>
                                <option value="Corporation">Corporation / Seminar</option>
                                <option value="Adult Birthday">Adult Birthday</option>
                                <option value="Kiddie Birthday">Kiddie Birthday</option>
                                <option value="Debut">Debut</option>
                                <option value="Christening">Christening</option>
                                <option value="Other">Other Occasion</option>
                            </select>
                        </div>

                        <div class="form-group" id="customTypeGroup" style="display:none; margin-top:10px;">
                            <label class="form-label" for="s1_customType">Specify Event Type <span class="required">*</span></label>
                            <input type="text" class="form-control" id="s1_customType" 
                                   placeholder="e.g. Anniversary, Reunion"
                                   maxlength="100"
                                   oninput="state.eventType = this.value"
                                   title="Enter the specific name of the occasion">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="s1_location">Event Venue / Address <span class="required">*</span></label>
                            <textarea class="form-control" id="s1_location"
                                      placeholder="Enter full address of the venue…"
                                      maxlength="500"
                                      oninput="onLocationChange(this.value)" rows="3"
                                      title="Complete address of where the event will be held"></textarea>
                            <div id="transportFeePanel" style="display:none; margin-top:12px; padding:12px; background:var(--sys-green-tint2); border-radius:12px; border:0.5px solid rgba(48,209,88,0.2);">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span style="font-size:13px; font-weight:700; color:var(--sys-green-deeper);">🚚 Outside Dasma Transport Fee</span>
                                    <div class="input-group" style="width:120px;">
                                        <span class="input-prefix" style="color:var(--sys-green-dark); font-size:12px;">₱</span>
                                        <input type="text" class="form-control" id="s1_transport" placeholder="0.00" autocomplete="off"
                                               style="font-size:13px; font-weight:700; text-align:right;" data-restrict="price"
                                               oninput="onTransportInput()" title="Additional transportation charge for locations outside Dasmariñas">
                                    </div>
                                </div>
                                <div style="font-size:10px; color:var(--label-3); margin-top:4px;">Address is outside Dasmariñas. Please enter fee manually.</div>
                            </div>
                        </div>

                        <!-- Availability status -->
                        <div id="availStatus" style="display:none; margin-top:14px;">
                            <!-- populated by JS -->
                        </div>
                    </div>
                </div>

                <!-- ══ STEP 2: Client & Event Details ══ -->
                <div class="stepper-panel" id="panel2">
                    <div style="max-width:560px; margin:0 auto;">
                        <h6 style="font-size:15px; font-weight:700; margin-bottom:4px;">👤 Who is the client?</h6>
                        <p style="font-size:13px; color:rgba(60,60,67,0.5); margin-bottom:22px;">Select an existing client or add a new one.</p>

                        <div class="form-group">
                            <label class="form-label">Client <span class="required">*</span></label>
                            <select class="form-control" id="s2_client" required>
                                <option value="">Loading clients…</option>
                            </select>
                        </div>

                        <!-- Quick new client toggle -->
                        <div style="margin-bottom:14px;">
                            <button type="button" onclick="toggleNewClient()"
                                style="background:none; border:none; color:var(--sys-green); font-size:13px; font-weight:600; cursor:pointer; padding:0; display:flex; align-items:center; gap:6px;">
                                <i class="fas fa-user-plus" style="font-size:12px;"></i>
                                <span id="newClientToggleLabel">+ Add new client instead</span>
                            </button>
                        </div>

                        <div id="newClientPanel" style="display:none; background:rgba(48,209,88,0.04); border:0.5px solid rgba(48,209,88,0.18); border-radius:13px; padding:16px; margin-bottom:16px;">
                            <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:rgba(60,60,67,0.4); margin-bottom:12px;">New Client Details</div>
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label class="form-label" for="nc_name">Full Name <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="nc_name" placeholder="Maria Santos" maxlength="100" title="First and Last name of the client">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="nc_phone">Phone Number <span class="required">*</span></label>
                                    <input type="tel" class="form-control" id="nc_phone" placeholder="09XXXXXXXXX" pattern="\d*" maxlength="11" data-restrict="phone" title="11-digit mobile number">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="nc_email">Email Address <span class="required">*</span></label>
                                    <input type="email" class="form-control" id="nc_email" placeholder="email@example.com" required maxlength="100" title="Email for digital receipts and confirmations">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="nc_messenger">Messenger/Facebook Link <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="nc_messenger" placeholder="m.me/username" required maxlength="100" title="Social media contact for quick communication">
                                </div>
                                <div class="form-group" style="grid-column: span 2;">
                                    <label class="form-label" for="nc_address">Address</label>
                                    <input type="text" class="form-control" id="nc_address" placeholder="City, Province" maxlength="255" title="Home or billing address">
                                </div>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Event Notes (Optional)</label>
                            <textarea class="form-control" id="s2_notes" rows="2" placeholder="e.g. VIP guest attending..., Themes to follow" maxlength="2000"></textarea>
                        </div>

                    </div>
                </div>

                <!-- Staff assignment removed — handled via Dispatching module after booking creation -->

                <!-- ══ STEP 4: Package & Dish Selection ══ -->
                <div class="stepper-panel" id="panel4">

                    <!-- Row 1: PAX + Price Card -->
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; align-items:start; margin-bottom:20px;">

                        <!-- Left: Pax input -->
                        <div>
                            <h6 style="font-size:15px; font-weight:700; margin-bottom:4px;">🍽️ How many guests?</h6>
                            <p style="font-size:13px; color:rgba(60,60,67,0.5); margin-bottom:18px;">
                                Enter guest count — the package is selected automatically.
                            </p>

                            <div class="form-group" style="margin-bottom:14px;">
                                <label class="form-label" style="font-size:13px; font-weight:700;">
                                    Select Package <span class="required">*</span>
                                </label>
                                <select class="form-control" id="s3_package" onchange="calcPricing()" style="font-size:15px; font-weight:600;">
                                    <option value="">Choosing package...</option>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom:6px; display:block;">
                                <label class="form-label" style="font-size:13px; font-weight:700;" for="s3_pax">
                                    Number of Guests (Pax) <span class="required">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="s3_pax"
                                           placeholder="e.g. <?= MIN_PAX + 25 ?>"
                                           style="font-size:20px; font-weight:700; letter-spacing:-0.3px;"
                                           data-restrict="number"
                                           oninput="calcPricing()"
                                           title="Total number of expected guests">
                                </div>
                                <div style="font-size:11.5px; color:rgba(60,60,67,0.4); margin-top:4px;">
                                    Min <?= MIN_PAX ?> guests, Max <?= MAX_PAX ?> guests.
                                </div>
                                <div id="s3_paxError" style="font-size:11.5px; color:#C0392B; margin-top:4px; display:none;"></div>
                            </div>

                            <!-- Auto-selected package badge -->
                            <div id="s3_pkgBadge" style="display:none; margin-top:14px;">
                                <div style="display:flex; align-items:center; gap:8px; padding:10px 14px;
                                     background:rgba(48,209,88,0.07); border:0.5px solid rgba(48,209,88,0.25);
                                     border-radius:12px;">
                                    <i class="fas fa-bolt" style="color:var(--sys-green); font-size:13px;"></i>
                                    <div>
                                        <div style="font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; color:rgba(60,60,67,0.4);">Package Auto-Selected</div>
                                        <div id="s3_pkgLabel" style="font-size:13px; font-weight:700; color:#1A7A32;"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Package Inclusions -->
                            <div id="s3_inclusionsBox" style="display:none; margin-top:14px; padding:14px 16px; background:rgba(48,209,88,0.04); border:0.5px solid rgba(48,209,88,0.2); border-radius:14px;">
                                <div style="font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:rgba(60,60,67,0.4); margin-bottom:10px; display:flex; align-items:center; gap:6px;">
                                    <i class="fas fa-list-check" style="color:var(--sys-green); font-size:11px;"></i> What's Included
                                </div>
                                <div id="s3_inclusionsList" style="display:flex; flex-wrap:wrap; gap:6px;"></div>
                            </div>

                            <!-- Dietary / Allergy Notes (Moved to Step 3) -->
                            <div class="form-group" style="margin-top:14px; margin-bottom:0;">
                                <label class="form-label" style="display:flex; align-items:center; gap:6px;" for="s3_dietaryNotes">
                                    <span style="font-size:15px;">⚠️</span> Allergy & Dietary Restrictions
                                    <span style="font-size:11px; font-weight:400; color:rgba(60,60,67,0.4);">(Optional)</span>
                                </label>
                                <textarea class="form-control" id="s3_dietaryNotes" rows="3"
                                          placeholder="e.g. 2 guests are lactose intolerant; no pork for 5 guests; less salt for the elderly…"
                                          maxlength="1000"
                                          style="border-color: rgba(255,149,0,0.4); background: rgba(255,149,0,0.03);"
                                          title="Inform the kitchen about any food allergies or dietary requirements"></textarea>
                                <div class="form-hint" style="color: rgba(180, 100, 0, 0.7);">This will be flagged on the grocery list and staff briefing.</div>
                            </div>
                        </div>

                        <!-- Right: Live Price Card -->
                        <div id="priceCard" style="background:rgba(255,255,255,0.9); border:0.5px solid rgba(48,209,88,0.2); border-radius:16px; padding:20px; box-shadow:0 4px 20px rgba(0,0,0,0.06);">
                            <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:rgba(60,60,67,0.4); margin-bottom:14px; display:flex; align-items:center; gap:6px;">
                                <i class="fas fa-calculator" style="color:var(--sys-green);"></i> Price Breakdown
                            </div>

                            <!-- Package tier chip -->
                            <div id="pr_tierRow" style="display:none; margin-bottom:12px; padding:8px 12px; background:rgba(48,209,88,0.06); border-radius:9px;">
                                <div style="font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; color:rgba(60,60,67,0.4); margin-bottom:2px;">Package Tier</div>
                                <div id="pr_tierName" style="font-size:13px; font-weight:700; color:#1A7A32;"></div>
                            </div>

                            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                                <div>
                                    <div style="font-size:12px; font-weight:600; color:rgba(0,0,0,0.75);">Base Package</div>
                                    <div id="pr_baseDesc" style="font-size:11px; color:rgba(60,60,67,0.45);"></div>
                                </div>
                                <span id="pr_basePrice" style="font-size:13px; font-weight:700; color:rgba(0,0,0,0.8);"></span>
                            </div>
                            <div id="pr_extraRow" style="display:none; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                                <div>
                                    <div style="font-size:12px; font-weight:600; color:rgba(0,0,0,0.75);">Extra Guests</div>
                                    <div id="pr_extraDesc" style="font-size:11px; color:rgba(60,60,67,0.45);"></div>
                                </div>
                                <span id="pr_extraCost" style="font-size:13px; font-weight:700; color:#FF9500;"></span>
                            </div>
                            <div id="pr_customFeeRow" style="display:none; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                                <div>
                                    <div style="font-size:12px; font-weight:600; color:rgba(0,0,0,0.75);">Menu Surcharges</div>
                                    <div style="font-size:11px; color:rgba(60,60,67,0.45);">Premium add-ons/Additional</div>
                                </div>
                                <span id="pr_customFeeCost" style="font-size:13px; font-weight:700; color:#FF3B30;"></span>
                            </div>
                            <div id="pr_transportRow" style="display:none; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                                <div>
                                    <div style="font-size:12px; font-weight:600; color:rgba(0,0,0,0.75);">Transport Fee</div>
                                    <div style="font-size:11px; color:rgba(60,60,67,0.45);">Manual entry</div>
                                </div>
                                <span id="pr_transportCost" style="font-size:13px; font-weight:700; color:var(--sys-green-deeper);"></span>
                            </div>
                            <div style="height:0.5px; background:rgba(60,60,67,0.1); margin:12px 0;"></div>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                <span style="font-size:14px; font-weight:800;">Total</span>
                                <span id="pr_total" style="font-size:22px; font-weight:900; color:var(--sys-green); letter-spacing:-0.8px;">₱—</span>
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span style="font-size:11px; color:rgba(60,60,67,0.45);">Rate per pax</span>
                                <span id="pr_perPax" style="font-size:11px; font-weight:600; color:rgba(60,60,67,0.45);"></span>
                            </div>
                            <div id="pr_dpNotice" style="display:none; margin-top:12px; padding:10px 12px; background:rgba(255,149,0,0.08); border-radius:9px; border:0.5px solid rgba(255,149,0,0.2);">
                                <div style="font-size:11px; font-weight:700; color:#9A5400; margin-bottom:2px;">Minimum Downpayment (<?= round(MIN_DP_PERCENT * 100) ?>%)</div>
                                <div id="pr_minDP" style="font-size:15px; font-weight:800; color:#9A5400;"></div>
                            </div>
                        </div>

                    </div>

                    <!-- Row 2: Dish Selection (full width) -->
                    <div id="dishSelectionPanel" style="display:none; padding-top:24px; border-top:1px solid rgba(60,60,67,0.06); margin-top:10px;">
                        <!-- Meal Type Filter (iOS Segmented Control Style) -->
                        <div class="meal-filter-container">
                            <button type="button" class="meal-filter-btn active" id="mf_all" onclick="setManualMealType('all')" title="Show all dishes">
                                <span>🌎</span> All
                            </button>
                            <button type="button" class="meal-filter-btn" id="mf_breakfast" onclick="setManualMealType('breakfast')" title="Filter by Breakfast dishes">
                                <span>🍳</span> Breakfast
                            </button>
                            <button type="button" class="meal-filter-btn" id="mf_lunch" onclick="setManualMealType('lunch')" title="Filter by Lunch dishes">
                                <span>🍱</span> Lunch
                            </button>
                            <button type="button" class="meal-filter-btn" id="mf_dinner" onclick="setManualMealType('dinner')" title="Filter by Dinner dishes">
                                <span>🍷</span> Dinner
                            </button>
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding:12px 16px; background:linear-gradient(to right, rgba(48,209,88,0.08), transparent); border-radius:12px; border-left:4px solid var(--sys-green);">
                            <div>
                                <div style="font-size:14px; font-weight:800; color:var(--sys-green-deeper);">Main Dishes</div>
                                <div style="font-size:11px; color:rgba(60,60,67,0.45); font-weight:500;">Choose up to <span id="maxMainLabel">5</span> items</div>
                            </div>
                            <div id="mainDishCounter" style="font-size:14px; font-weight:800; color:#1A7A32; background:#fff; padding:4px 10px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.04);">0 / 5</div>
                        </div>
                        <div id="mainDishGrid" style="margin-bottom:24px;"></div>

                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding:12px 16px; background:linear-gradient(to right, rgba(255,149,0,0.08), transparent); border-radius:12px; border-left:4px solid #FF9500;">
                            <div>
                                <div style="font-size:14px; font-weight:800; color:#9A5400;">Desserts</div>
                                <div style="font-size:11px; color:rgba(60,60,67,0.45); font-weight:500;">Choose up to <span id="maxDessertLabel">1</span> item</div>
                            </div>
                            <div id="dessertCounter" style="font-size:14px; font-weight:800; color:#9A5400; background:#fff; padding:4px 10px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.04);">0 / 1</div>
                        </div>
                        <div id="dessertDishGrid" style="margin-bottom:24px;"></div>

                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding:12px 16px; background:linear-gradient(to right, var(--sys-green-tint), transparent); border-radius:12px; border-left:4px solid var(--sys-green);">
                            <div>
                                <div style="font-size:14px; font-weight:800; color:var(--sys-green-deeper);">Rice Selection</div>
                                <div style="font-size:11px; color:rgba(60,60,67,0.45); font-weight:500;">Select rice option</div>
                            </div>
                        </div>
                        <div id="riceDishGrid" style="margin-bottom:24px;"></div>

                        <div id="additionalDishGrid"></div>
                    </div>

                        <!-- ── CUSTOM ADD-ONS SECTION ── -->
                        <div style="margin-top:20px; padding-top:20px; border-top:0.5px solid rgba(60,60,67,0.08);">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                                <div>
                                    <div style="font-size:13px; font-weight:800; color:var(--label);">✨ Custom Add-ons & Extra Food</div>
                                    <div style="font-size:11px; color:rgba(60,60,67,0.45);">Add off-menu items or special requests with custom prices</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-success" onclick="addCustomItemRow()" style="padding:6px 14px; border-radius:10px;">
                                    <i class="fas fa-plus me-1"></i> Add Item
                                </button>
                            </div>

                            <!-- Custom Items Container -->
                            <!-- Custom Items Container -->
                            <div id="customItemsContainer" style="display:grid; gap:8px;"></div>
                            <div id="noCustomItemsHint" style="padding:20px; text-align:center; background:rgba(60,60,67,0.02); border:1px dashed rgba(60,60,67,0.1); border-radius:12px; font-size:12px; color:rgba(60,60,67,0.4);">
                                No custom items added yet.
                            </div>
                        </div>
                </div>

                <!-- ══ STEP 5: Summary, Downpayment & T&C ══ -->
                <div class="stepper-panel" id="panel5">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; align-items:start;">

                        <!-- Left: Booking Summary -->
                        <div>
                            <h6 style="font-size:15px; font-weight:700; margin-bottom:16px;">📋 Booking Summary</h6>
                            <div id="summaryCard" style="background:rgba(242,242,247,0.7); border-radius:14px; padding:18px; font-size:13px; line-height:1.8;">
                                <!-- populated by buildSummary() -->
                            </div>
                        </div>

                        <!-- Right: Downpayment + T&C -->
                        <div>
                            <h6 style="font-size:15px; font-weight:700; margin-bottom:16px;">💳 Downpayment</h6>

                            <!-- Balance card -->
                            <div style="background:rgba(48,209,88,0.05); border:0.5px solid rgba(48,209,88,0.2); border-radius:14px; padding:16px; margin-bottom:14px;">
                                <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                                    <span style="font-size:13px; color:rgba(60,60,67,0.6);">Total Cost</span>
                                    <span style="font-weight:700;" id="s4_total">—</span>
                                </div>
                                <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                                    <span style="font-size:13px; color:rgba(60,60,67,0.6);">Minimum Downpayment (<?= round(MIN_DP_PERCENT * 100) ?>%)</span>
                                    <span style="font-weight:700; color:#9A5400;" id="s4_minDP">—</span>
                                </div>
                                <div style="height:0.5px; background:rgba(60,60,67,0.1); margin:10px 0;"></div>
                                <div style="display:flex; justify-content:space-between;">
                                    <span style="font-size:13px; font-weight:700;">Remaining Balance</span>
                                    <span style="font-size:16px; font-weight:800; color:#C0392B;" id="s4_balance">—</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" id="dpLabel" for="s4_dp">
                                    Downpayment Amount (₱)
                                    <span style="font-size:11px; font-weight:400; color:rgba(60,60,67,0.4);"> — minimum <?= round(MIN_DP_PERCENT * 100) ?>% required (<?= round(RUSH_DP_PERCENT * 100) ?>% within <?= round(RUSH_THRESHOLD_HOURS / 24) ?> days)</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-prefix">₱</span>
                                    <input type="text" class="form-control" id="s4_dp"
                                           placeholder="0.00" data-restrict="price"
                                           oninput="onDPInput()"
                                           title="Amount to be paid initially to confirm the booking">
                                </div>
                                <div id="s4_dpError" style="font-size:11.5px; color:#C0392B; margin-top:4px; display:none;"></div>
                            </div>

                            <div class="form-grid-2" style="gap:10px;">
                                <div class="form-group">
                                    <label class="form-label">Payment Method</label>
                                    <select class="form-control" id="s4_dpMethod" onchange="onDPMethodChange()">
                                        <option value="cash">💵 Cash</option>
                                        <option value="gcash">📱 GCash</option>
                                        <option value="maya">📱 Maya</option>
                                        <option value="bank_transfer">🏦 Bank Transfer</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" id="s4_dpRefLabel">Reference No.</label>
                                    <input type="text" class="form-control" id="s4_dpRef"
                                           placeholder="e.g. GC-2026041412345"
                                           maxlength="40"
                                           oninput="this.value=this.value.replace(/[^a-zA-Z0-9\-_]/g,'')"
                                           title="Alphanumeric reference (GCash, Maya, bank trace no.)">
                                </div>
                            </div>

                            <!-- Terms & Conditions -->
                            <div style="background:rgba(255,59,48,0.04); border:0.5px solid rgba(255,59,48,0.15); border-radius:12px; padding:14px; margin-top:6px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                    <div style="font-size:11px; font-weight:700; color:#C0392B; text-transform:uppercase; letter-spacing:0.5px;">Terms & Conditions</div>
                                    <button type="button" onclick="Modal.open('termsModal')" style="background:none; border:none; padding:0; color:#C0392B; font-size:11.5px; font-weight:600; cursor:pointer; text-decoration:underline;">View Terms</button>
                                </div>
                                <p style="font-size:11.5px; color:rgba(60,60,67,0.65); line-height:1.7; margin-bottom:10px;">
                                    <?= nl2br(htmlspecialchars($dynamicTerms)) ?>
                                </p>
                                <div style="font-size:10px; font-weight:700; color:rgba(60,60,67,0.3); text-transform:uppercase; margin-bottom:4px;">Data Privacy Notice</div>
                                <p style="font-size:11.5px; color:rgba(60,60,67,0.65); line-height:1.7; margin-bottom:10px;">
                                    <?= nl2br(htmlspecialchars($dynamicPrivacy)) ?>
                                </p>
                                <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                    <input type="checkbox" id="s4_terms" onchange="onTermsChange()"
                                           style="width:16px; height:16px; accent-color:var(--sys-green);">
                                    <span style="font-size:12px; font-weight:600; color:rgba(0,0,0,0.75);">
                                        I have read and agree to the Terms & Conditions.
                                    </span>
                                </label>
                            </div>
                        </div> <!-- /right-col -->
                    </div> <!-- /grid -->
                </div> <!-- /panel5 -->
            </div><!-- /modal-body -->

            <!-- ── FOOTER NAVIGATION ── -->
            <div class="modal-footer" style="padding:16px 24px; border-top:0.5px solid rgba(60,60,67,0.08); gap:12px;">
                <button class="btn btn-secondary" id="stepPrevBtn" onclick="stepGo(-1)" style="display:none; padding:10px 20px; border-radius:12px; font-weight:600;">
                    <i class="fas fa-chevron-left me-2"></i> Back
                </button>
                <div style="flex:1;"></div>
                <button type="button" class="btn btn-outline-secondary" onclick="closeBookingStepper()" style="padding:10px 20px; border-radius:12px; font-weight:600;">Cancel</button>
                <button class="btn btn-primary" id="stepNextBtn" onclick="stepGo(1)" style="padding:10px 28px; border-radius:12px; font-weight:700;">
                    Next <i class="fas fa-chevron-right ms-2"></i>
                </button>
                <button class="btn btn-success" id="stepSubmitBtn" style="display:none; padding:10px 28px; border-radius:12px; font-weight:700;" onclick="submitBooking()">
                    Confirm Booking <i class="fas fa-check-circle ms-2"></i>
                </button>
            </div>

        </div>
    </div>
</div>

<!-- ══ STEPPER STYLES ══ -->
<style>
.stepper-step .stepper-circle {
    width: 38px !important;
    height: 38px !important;
    background: rgba(120,120,128,0.12);
    color: rgba(60,60,67,0.45);
    border: 1.5px solid rgba(60,60,67,0.1);
    font-size: 15px !important;
}
.stepper-step > div:last-child { 
    font-size: 11px !important;
    font-weight: 700 !important;
    color: rgba(60,60,67,0.3);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.stepper-step.active .stepper-circle {
    background: var(--sys-green) !important;
    color: #fff !important;
    border-color: var(--sys-green) !important;
    box-shadow: 0 6px 16px rgba(48,209,88,0.35) !important;
}
.stepper-step.active > div:last-child { color: var(--sys-green-dark); }
.stepper-step.done .stepper-circle {
    background: rgba(48,209,88,0.1) !important;
    color: var(--sys-green-dark) !important;
    border-color: rgba(48,209,88,0.25) !important;
}
.stepper-step.done > div:last-child { color: rgba(60,60,67,0.6); }
.stepper-line {
    height: 2px !important;
    background: rgba(60,60,67,0.08) !important;
}
.stepper-line.done { background: var(--sys-green) !important; opacity: 0.4; }

.stepper-panel { display: none; }
.stepper-panel.active { display: block; animation: panelFade 0.3s ease-out; }
@keyframes panelFade { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

/* Availability badges */
.avail-ok  { display:flex; align-items:center; gap:12px; padding:16px 20px; background:rgba(48,209,88,0.08); border:1px solid rgba(48,209,88,0.2); border-radius:16px; font-size:14px; font-weight:700; color:#1A7A32; }
.avail-no  { display:flex; align-items:center; gap:12px; padding:16px 20px; background:rgba(255,59,48,0.08); border:1px solid rgba(255,59,48,0.2); border-radius:16px; font-size:14px; font-weight:700; color:#C0392B; }
.avail-chk { display:flex; align-items:center; gap:12px; padding:16px 20px; background:rgba(255,149,0,0.08); border:1px solid rgba(255,149,0,0.2); border-radius:16px; font-size:14px; color:#9A5400; font-weight:600; }

/* Dish selection grid */
.dish-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 10px;
}
/* Meal Type Filter Styles */
.meal-filter-container {
    margin: 0 auto 24px auto;
    display: flex;
    align-items: center;
    background: rgba(60,60,67,0.05);
    padding: 3px;
    border-radius: 12px;
    max-width: 420px;
    border: 0.5px solid rgba(60,60,67,0.05);
}
.meal-filter-btn {
    flex: 1;
    border: none;
    background: none;
    border-radius: 9px;
    padding: 7px 4px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    color: rgba(60,60,67,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.meal-filter-btn:hover {
    color: var(--label);
}
.meal-filter-btn.active {
    background: #fff;
    color: var(--label);
    font-weight: 700;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
}

.dish-card {
    position: relative;
    border: 1px solid rgba(60,60,67,0.08);
    border-radius: 12px;
    padding: 10px 14px;
    cursor: pointer;
    transition: all 0.2s var(--ease-spring);
    background: #fff;
    display: flex;
    align-items: center;
    gap: 12px;
    user-select: none;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}
.dish-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    border-color: rgba(48,209,88,0.3);
}
.dish-card .dish-icon-container {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: rgba(60,60,67,0.03);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}
.dish-card .dish-info {
    flex: 1;
    min-width: 0;
}
.dish-card .dish-name {
    font-size: 13px;
    font-weight: 600;
    color: var(--label);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.dish-card .dish-meta {
    font-size: 9px;
    color: rgba(60,60,67,0.4);
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-top: 1px;
}
/* All Category Selected State (Unified Green Theme) */
.dish-card.selected {
    border-color: var(--sys-green);
    background: #F2FBF4;
    box-shadow: 0 2px 8px rgba(48,209,88,0.1);
}
.dish-card.selected .dish-name {
    color: var(--sys-green-deeper);
    font-weight: 700;
}
.dish-card.selected .dish-icon-container {
    background: rgba(48,209,88,0.1);
}
.dish-card.selected::after {
    content: '\2713';
    position: absolute;
    top: 8px; right: 10px;
    font-size: 11px;
    font-weight: 900;
    color: var(--sys-green);
}
/* Surcharge Indicator */
.dish-card.surcharged {
    border-style: dashed;
}
.dish-card.surcharged::before {
    content: 'EXTRA';
    position: absolute;
    bottom: -8px; right: 8px;
    background: #FF9500;
    color: white;
    font-size: 9px;
    padding: 2px 6px;
    border-radius: 6px;
    font-weight: 800;
    box-shadow: 0 2px 6px rgba(255,149,0,0.3);
    z-index: 5;
}
.dish-card.disabled {
    opacity: 0.4;
    cursor: not-allowed;
    filter: grayscale(1);
}

@media (max-width:768px) {
    #panel4 > div:first-child, #panel5 > div:first-child { grid-template-columns: 1fr !important; }
    .dish-grid { grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); }
}
</style>
<!-- ══ TERMS AND CONDITIONS MODAL ══ -->
<div class="modal fade" id="termsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:18px;">
            <div class="modal-header" style="position:relative;">
                <h5 class="modal-title" style="font-weight:800; padding-right:40px;">Terms & Conditions</h5>
                <button type="button" class="btn-close" onclick="Modal.close('termsModal')" style="position:absolute; right:20px; top:20px;"></button>
            </div>
            <div class="modal-body" style="padding:24px; max-height:60vh; overflow-y:auto; font-size:13px; color:var(--label-2); line-height:1.7;">
                <div style="font-weight:800; color:var(--label); margin-bottom:8px; text-transform:uppercase; font-size:11px;">Legal Terms & Conditions</div>
                <div style="margin-bottom:20px; white-space: pre-line;"><?= htmlspecialchars($dynamicTerms) ?></div>
                
                <div style="font-weight:800; color:var(--label); margin-bottom:8px; text-transform:uppercase; font-size:11px;">Data Privacy Notice</div>
                <div style="white-space: pre-line;"><?= htmlspecialchars($dynamicPrivacy) ?></div>
            </div>
            <div class="modal-footer" style="padding:16px;">
                <button class="btn btn-primary btn-full" onclick="Modal.close('termsModal')">I Understand</button>
            </div>
        </div>
    </div>
</div>

<!-- ══ STEPPER JAVASCRIPT ══ -->
<script>
(function () {
    /* ── HELPERS ─────────────────────────────────────────────── */
    const esc = (s) => String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

    // ── SYSTEM CONSTANTS (DYNAMIC) ──────────────────────────────
    const minPax = <?= MIN_PAX ?>;
    const maxPax = <?= MAX_PAX ?>;
    const minDpPct = <?= MIN_DP_PERCENT ?>;
    const rushDpPct = <?= RUSH_DP_PERCENT ?>;
    const rushThresholdHours = <?= RUSH_THRESHOLD_HOURS ?>;
    const opStart = '<?= OPERATING_HOURS_START ?>';
    const opEnd = '<?= OPERATING_HOURS_END ?>';

    // Dynamic Dish Limits & Surcharge Rates
    const defaultMaxMain = <?= DEFAULT_MAX_MAIN ?>;
    const defaultMaxDessert = <?= DEFAULT_MAX_DESSERT ?>;
    const defaultMaxRice = <?= DEFAULT_MAX_ADDITIONAL ?>;
    const defaultRateMain = <?= EXTRA_MAIN_RATE ?>;
    const defaultRateDessert = <?= EXTRA_DESSERT_RATE ?>;
    const defaultRateRice = <?= EXTRA_RICE_RATE ?>;

    const mealB   = <?= MEAL_BREAKFAST_START ?>;
    const mealL   = <?= MEAL_LUNCH_START ?>;
    const mealD   = <?= MEAL_DINNER_START ?>;

    /* ── STATE ────────────────────────────────────────────────── */
    const state = {
        step:           1,
        date:           '',
        time:           '',
        location:       '',
        available:      true,
        eventType:      'Wedding',
        transportFee:   0,
        clientId:       null,
        isNewClient:    false,
        status:         'confirmed',
        notes:          '',
        // Package / pricing
        packageId:      null,
        packageData:    null,
        pax:            0,
        tierPax:        <?= MIN_PAX ?>,
        totalCost:      0,
        basePrice:       0,
        extraPax:       0,
        extraCost:      0,
        // Dish selection
        selectedMain:   [],   // array of dish IDs
        selectedDesserts: [], // array of dish IDs
        selectedAdditional: [], // array of dish IDs
        maxMain:        <?= DEFAULT_MAX_MAIN ?>,
        maxDessert:     <?= DEFAULT_MAX_DESSERT ?>,
        maxRice:        <?= DEFAULT_MAX_ADDITIONAL ?>,
        mealType:       'all', // all, breakfast, lunch, dinner
        // Payment
        dpAmount:       0,
        dpMethod:       'cash',
        dpRef:          '',
        termsOk:        false,
        // Dietary
        dietaryNotes:   '',
        // Custom / Additional Foods
        customItems:    [], // array of {name, category, price, notes}
        // Rates
        additionalRates: {
            main: 50,
            dessert: 30,
            rice: 20
        }
    };

    let allPackages = [];
    let allDishes   = [];
    let allDishesGroups = {};
    let allClients  = [];
    let availTimer  = null;
    let bookedDates = []; // Calendar guard: pre-fetched taken dates

    // Expose state globally so HTML event handlers can access it
    window.state = state;

    /* ── INIT ────────────────────────────────────────────────── */
    window.openBookingStepper = async function () {
        resetStepper();
        try {
            const [pkgData, dishData, cdData, avData] = await Promise.all([
                Api.get(BASE + 'src/api/packages.php'),
                Api.get(BASE + 'src/api/packages.php', { dishes: 1 }),
                Api.get(BASE + 'src/api/clients.php'),
                Api.get(BASE + 'src/api/availability.php', { booked_dates: 1 }),
            ]);
            allPackages          = pkgData.packages   || [];
            state.additionalRates.main    = pkgData.rates.extra_main_rate || defaultRateMain;
            state.additionalRates.dessert = pkgData.rates.extra_dessert_rate || defaultRateDessert;
            state.additionalRates.rice    = pkgData.rates.extra_rice_rate || defaultRateRice;

            allDishesGroups      = dishData.dishes_grouped || {};
            allDishes            = [];
            Object.values(allDishesGroups).forEach(arr => { allDishes = allDishes.concat(arr); });
            allClients           = cdData.clients      || [];
            bookedDates          = avData.booked_dates || [];
            buildClientSelect();
            buildPackageSelect();
            buildDishSelection();
            
            // Re-attach date listeners just in case
            const dateIn = document.getElementById('s1_date');
            if (dateIn) {
                dateIn.addEventListener('input', (e) => {
                    state.date = e.target.value;
                    window.checkAvailability();
                });
            }
        } catch(e) { Toast.error('Failed to load form data.'); }
        Modal.open('bookingStepperModal');
    };

    window.closeBookingStepper = function () {
        Modal.close('bookingStepperModal');
    };

    function buildPackageSelect() {
        const sel = document.getElementById('s3_package');
        // Get unique set names
        const names = [...new Set(allPackages.map(p => p.set_name))];
        sel.innerHTML = '<option value="">Select a package...</option>' +
            names.map(name => `<option value="${name}">${name}</option>`).join('');
    }

    function resetStepper() {
        Object.assign(state, {
            step:1, date:'', time:'', location:'',
            available:true, eventType:'Wedding', transportFee:0,
            clientId:null, isNewClient:false,
            status:'confirmed', notes:'',
            packageId:null, packageData:null,
            pax:0, tierPax: <?= MIN_PAX ?>, totalCost:0, basePrice:0, extraPax:0, extraCost:0, customFees:0,
            selectedMain:[], selectedDesserts:[], selectedAdditional:[], maxMain:5, maxDessert:1,
            dpAmount:0, dpMethod:'cash', dpRef:'', termsOk:false
        });
        document.getElementById('s1_date').value     = '';
        document.getElementById('s1_time').value     = '';
        document.getElementById('s1_location').value = '';
        document.getElementById('s1_type').value     = 'Wedding';
        document.getElementById('customTypeGroup').style.display = 'none';
        document.getElementById('s1_customType').value = '';
        document.getElementById('s1_transport').value = 0;
        document.getElementById('transportFeePanel').style.display = 'none';
        document.getElementById('s2_notes').value    = '';
        const dietaryEl = document.getElementById('s3_dietaryNotes');
        if (dietaryEl) dietaryEl.value = '';
        document.getElementById('s3_pax').value      = '';
        document.getElementById('s4_dp').value       = '';
        document.getElementById('s4_terms').checked  = false;
        document.getElementById('s3_pkgBadge').style.display   = 'none';
        document.getElementById('pr_tierRow').style.display    = 'none';
        document.getElementById('pr_dpNotice').style.display   = 'none';
        document.getElementById('pr_total').textContent        = '₱—';
        document.getElementById('dishSelectionPanel').style.display = 'none';
        
        // Reset limits to fixed 5/1/1
        state.maxMain = 5;
        state.maxDessert = 1;
        state.maxRice = 1;
        state.mealType = 'all';

        setStep(1);
        updateAvailUI(null);
    }

    /* ── STEP NAVIGATION ── */
    window.stepGo = async function (dir) {
        const target = state.step + dir;
        if (dir > 0 && !(await validateStep(state.step))) return;
        setStep(target);
    };

    function setStep(n) {
        const total = 4;
        state.step = Math.max(1, Math.min(total, n));

        // Update panels — panels are: panel1(Date), panel2(Client), panel4(Package), panel5(Summary)
        const panelMap = [1, 2, 4, 5]; // DOM panel IDs for steps 1-4
        panelMap.forEach((pid, idx) => {
            const panel = document.getElementById('panel' + pid);
            if (panel) panel.classList.toggle('active', idx + 1 === state.step);
        });

        // Update nav circles
        for (let i = 1; i <= total; i++) {
            const el = document.getElementById('stepNav' + i);
            if (!el) continue;
            el.classList.remove('active','done');
            if (i === state.step) el.classList.add('active');
            if (i < state.step)  el.classList.add('done');
        }

        // Update lines
        for (let i = 1; i < total; i++) {
            const line = document.getElementById('stepLine' + i);
            if (line) line.classList.toggle('done', i < state.step);
        }

        // Update buttons
        document.getElementById('stepPrevBtn').style.display   = state.step > 1    ? 'inline-flex' : 'none';
        document.getElementById('stepNextBtn').style.display   = state.step < total ? 'inline-flex' : 'none';
        document.getElementById('stepSubmitBtn').style.display = state.step === total ? 'inline-flex' : 'none';
        document.getElementById('stepperSubtitle').textContent = `Step ${state.step} of ${total}`;

        // ── Step hooks ──
        if (state.step === 3) {
             document.getElementById('s3_pax').value = state.pax;
             refreshPackageCalc(); 
        }
        if (state.step === 4) buildSummary();
    }

    /* ── STEP VALIDATION ── */
    async function validateStep(step) {
        if (step === 1) {
            const d = document.getElementById('s1_date').value;
            if (!d) { Toast.error('Please select an event date.'); return false; }
            const dObj = new Date(d);
            const today = new Date();
            today.setHours(0,0,0,0);
            dObj.setHours(0,0,0,0);
            const diffDays = (dObj - today) / (1000*60*60*24);
            if (diffDays < 1) { Toast.error('Booking date must be at least tomorrow.'); return false; }
            if (diffDays > 365) { Toast.error('Bookings cannot be made more than 1 year in advance.'); return false; }
            if (!state.available) { Toast.error('This date is already taken. Please choose another.'); return false; }
            state.date = d;
            
            let timeVal = document.getElementById('s1_time').value;
            // Safari/Mobile fallback: Check both ID and querySelector, then the internal state
            if (!timeVal) {
                const el = document.querySelector('#s1_time');
                if (el) timeVal = el.value;
            }
            if (!timeVal && state.time) timeVal = state.time; 

            if (!timeVal) { Toast.error('Please select an event time.'); return false; }
            const parts = timeVal.split(':');
            const hour = parseInt(parts[0], 10);
            const opStartHour = parseInt((opStart || '07:00').split(':')[0], 10);
            const opEndHour   = parseInt((opEnd   || '23:00').split(':')[0], 10);
            if (hour < opStartHour || hour >= opEndHour) {
                Toast.error(`Event hours strictly limited between ${Format.time(opStart)} and ${Format.time(opEnd)}.`); return false;
            }
            state.time = timeVal;
            
            const loc = document.getElementById('s1_location').value.trim();
            if(!loc) { Toast.error('Event Venue is required.'); return false; }
            state.location = loc;
            
            return true;
        }

       function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(String(email).toLowerCase());
}

        if (step === 2) {
            if (state.isNewClient) {
                const name  = document.getElementById('nc_name').value.trim();
                const phone = document.getElementById('nc_phone').value.trim();
                const email = document.getElementById('nc_email').value.trim();
                const address = document.getElementById('nc_address').value.trim();
                const messenger_link =document.getElementById('nc_messenger').value.trim();
                
                if (!name) { 
                    Toast.error('New client name are required.'); 
                    return false; 
                }

                if (!phone) { 
                    Toast.error('New client phone required.'); 
                    return false; 
                }

                if (phone.length < 11 || !phone.startsWith('09')) {
                    Toast.error('Please enter a valid 11-digit PH mobile number.');
                    return false;
                }

                if (!messenger_link) {
                    Toast.error('New client messenger/facebook link are required.'); 
                    return false; 
                }

                if (!email) { 
                    Toast.error('New client email are required.'); 
                    return false; 
                }

                if (!validateEmail(email)) { 
                    Toast.error('Invalid email address.'); 
                    return false; 
                }


                // Save new client
                try {
                    const r = await Api.post(BASE + 'src/api/clients.php', {
                        name, phone,
                        email:   document.getElementById('nc_email').value.trim(),
                        address: document.getElementById('nc_address').value.trim(),
                        messenger_link: document.getElementById('nc_messenger').value.trim(),
                    });
                    state.clientId = r.id;
                    Toast.success('Client added!');
                    // Refresh client list
                    const cd = await Api.get(BASE + 'src/api/clients.php');
                    allClients = cd.clients || [];
                    buildClientSelect();
                } catch(e) { Toast.error(e.message); return false; }
            } else {
                const cid = document.getElementById('s2_client').value;
                if (!cid) { Toast.error('Please select a client.'); return false; }
                state.clientId = cid;
            }
            state.status       = 'pending'; // default to pending until DP is confirmed
            state.notes        = document.getElementById('s2_notes').value;
            state.eventType    = document.getElementById('s1_type').value === 'Other' ? document.getElementById('s1_customType').value : document.getElementById('s1_type').value;
            state.transportFee = parseFloat(document.getElementById('s1_transport').value) || 0;
            return true;
        }

        if (step === 3) {
            // Pax validation (moved from removed staff step)
            const pax = state.pax;
            if (pax < minPax) { Toast.error('Minimum guest count is ' + minPax + '.'); return false; }
            if (pax > maxPax) { Toast.error('Maximum guest count is ' + maxPax + '.'); return false; }
            // Removed 5-pax increments check


            if (!state.packageData) { Toast.error('Could not determine package. Check pax count.'); return false; }
            if (!state.totalCost)   { Toast.error('Price could not be calculated.'); return false; }

            // Dish validation
            if (state.selectedMain.length === 0) {
                Toast.error('Please select at least 1 main dish.'); return false;
            }
            if (state.selectedDesserts.length === 0) {
                Toast.error('Please choose at least 1 dessert.'); return false;
            }

            state.dietaryNotes = (document.getElementById('s3_dietaryNotes')?.value || '').trim();

            return true;
        }

        if (step === 4) {
            const eventDate = new Date(state.date);
            const now       = new Date();
            const diffHours = (eventDate - now) / (1000 * 60 * 60);
            const isLastMinute = diffHours < rushThresholdHours;
            const dpPct        = isLastMinute ? rushDpPct : minDpPct;

            const dp = parseFloat(document.getElementById('s4_dp').value) || 0;
            const minDP = Math.ceil(state.totalCost * dpPct * 100) / 100;

            if (dp < minDP - 0.01) { 
                const rushDays = Math.round(rushThresholdHours / 24);
                Toast.error(isLastMinute ? `${Math.round(rushDpPct * 100)}% payment is required for bookings made within ${rushDays} days (${rushThresholdHours}h).` : `Downpayment is below the ${Math.round(minDpPct * 100)}% minimum (₱${minDP.toLocaleString()}).`); 
                return false; 
            }

            // Reference No. Validation for digital payments
            const method = document.getElementById('s4_dpMethod').value;
            const ref = document.getElementById('s4_dpRef').value.trim();
            if (method !== 'cash' && !ref) {
                Toast.error(`Reference number is required for ${method.toUpperCase()} payments.`);
                return false;
            }

            if (!document.getElementById('s4_terms').checked) { Toast.error('You must accept the Terms and Conditions.'); return false; }
            state.dpAmount = dp;
            return true;
        }
        
        return true;
    }

    /* ── AVAILABILITY CHECK ── */
    window.checkAvailability = function () {
        const date = document.getElementById('s1_date').value;
        if (!date) { updateAvailUI(null); return; }

        // ── Instant calendar guard: check pre-fetched bookedDates ──
        if (bookedDates.includes(date)) {
            state.available = false;
            updateAvailUI('taken', { id: '?', client_name: 'Another event', event_time: null, event_location: '' });
            return;
        }

        updateAvailUI('checking');
        clearTimeout(availTimer);
        availTimer = setTimeout(async () => {
            try {
                const d = await Api.get(BASE + 'src/api/availability.php', { date });
                if (d.available) {
                    state.available = true;
                    updateAvailUI('ok');
                } else {
                    state.available = false;
                    updateAvailUI('taken', d.booking);
                    Toast.warning('This date is already booked. Please choose another.', 'Date Conflict');
                }
            } catch(e) {
                state.available = false;
                updateAvailUI('error');
            }
        }, 600);
    };

    function updateAvailUI(status, booking) {
        const el  = document.getElementById('availStatus');
        const btn = document.getElementById('stepNextBtn');
        if (!status) { el.style.display = 'none'; btn.disabled = false; return; }
        el.style.display = 'block';
        if (status === 'checking') {
            el.innerHTML = `<div class="avail-chk"><i class="fas fa-circle-notch fa-spin"></i> Checking availability…</div>`;
            btn.disabled = true;
        } else if (status === 'ok') {
            el.innerHTML = `<div class="avail-ok"><i class="fas fa-circle-check"></i> Date is available! You're good to go.</div>`;
            btn.disabled = false;
        } else if (status === 'taken') {
            const b = booking;
            el.innerHTML = `<div class="avail-no">
                <i class="fas fa-circle-xmark"></i>
                <div>
                    <strong>Date is taken</strong><br>
                    <span style="font-weight:400; font-size:12px;">
                        Booking #${b.id} — ${b.client_name}${b.event_time ? ' at ' + b.event_time : ''}
                        ${b.event_location ? ' · ' + b.event_location : ''}
                    </span>
                </div>
            </div>`;
            btn.disabled = true;
        } else {
            el.innerHTML = `<div class="avail-no"><i class="fas fa-exclamation-triangle"></i> Could not verify. Please try again.</div>`;
            btn.disabled = true;
        }
    }

    /* ── CLIENT SELECT ── */
    function buildClientSelect() {
        const sel = document.getElementById('s2_client');
        sel.innerHTML = '<option value="">Select client…</option>' +
            allClients.map(c => `<option value="${c.id}">${c.name} — ${c.phone}</option>`).join('');
        if (state.clientId) sel.value = state.clientId;
    }

    window.toggleNewClient = function () {
        state.isNewClient = !state.isNewClient;
        document.getElementById('newClientPanel').style.display = state.isNewClient ? 'block' : 'none';
        document.getElementById('s2_client').style.display      = state.isNewClient ? 'none'  : 'block';
        document.getElementById('newClientToggleLabel').textContent =
            state.isNewClient ? '← Use existing client instead' : '+ Add new client instead';
    }

    /* ── TRANSPORT FEE LOGIC ── */
    window.onLocationChange = function (val) {
        state.location = val;
        const panel = document.getElementById('transportFeePanel');
        const lowVal = val.toLowerCase();
        const isOutside = val.length > 2 && !lowVal.includes('dasma') && !lowVal.includes('dasmariñas') && !lowVal.includes('dasmarinas');
        panel.style.display = isOutside ? 'block' : 'none';
        if (!isOutside) {
            document.getElementById('s1_transport').value = 0;
            state.transportFee = 0;
            calcPricing();
        }
    }

    window.onTransportInput = function () {
        const el = document.getElementById('s1_transport');
        let val = parseFloat(el.value) || 0;
        if (val < 0) {
            val = 0;
            el.value = 0;
        }
        state.transportFee = val;
        calcPricing();
    }

    window.onTimeChange = function (val) {
        state.time = val;
        if (!val) return;

        if (val < opStart || val >= opEnd) {
            Toast.warning(`Operating hours are from ${Format.time(opStart)} to ${Format.time(opEnd)}. Please adjust your selection.`);
        }

        // Optional: Suggest meal type based on time but DON'T override the manual filter
        // If state.mealType is 'all', we might want to auto-select if it's the first time
        if (state.mealType === 'all' || !val) {
             const h = parseInt(val.split(':')[0], 10);
             let suggestedType = 'all';
             if (h >= mealB && h < mealL) suggestedType = 'breakfast';
             else if (h >= mealL && h < mealD) suggestedType = 'lunch';
             else if (h >= mealD) suggestedType = 'dinner';
             
             // We can auto-set if they haven't manually changed it yet
             // For now, let's just stick to 'All' as requested: "display all but add filter"
        }

        // Refresh dish selection UI based on meal type
        buildDishSelection();
        calcPricing();
    };

    window.onEventTypeChange = function (val) {
        state.eventType = val;
        const customGroup = document.getElementById('customTypeGroup');
        const customInput = document.getElementById('s1_customType');
        
        if (val === 'Other') {
            customGroup.style.display = 'block';
            state.eventType = customInput.value;
        } else {
            customGroup.style.display = 'none';
        }
        updateStaffMin();
    };

    // Update pricing when pax changes
    window.updateStaffMin = function() {
        let p = parseInt(document.getElementById('s3_pax')?.value) || state.pax || 0;
        if (p > maxPax) {
            p = maxPax;
            const paxEl = document.getElementById('s3_pax');
            if (paxEl) paxEl.value = p;
            Toast.warning(`Maximum guest count is capped at ${maxPax}.`);
        }

        state.pax = p;
        
        // Trigger pricing calculation
        if (typeof calcPricing === 'function') calcPricing();
    };

    /* ── PRICING ENGINE (PAX-DRIVEN AUTO-TIER from DB packages) ── */
    window.refreshPackageCalc = function () {
        calcPricing();
    };

    window.calcPricing = function () {
        const pax    = parseInt(document.getElementById('s3_pax').value) || 0;
        state.pax = pax;

        const errEl  = document.getElementById('s3_paxError');
        const badge  = document.getElementById('s3_pkgBadge');
        const tierRow = document.getElementById('pr_tierRow');
        const dishPanel = document.getElementById('dishSelectionPanel');

        // Always hide error initially to clear previous state
        errEl.innerText = '';
        errEl.style.display = 'none';

        const selectedSetName = document.getElementById('s3_package').value;
        if (!pax || !state.date || !selectedSetName) {
            badge.style.display    = 'none';
            tierRow.style.display  = 'none';
            dishPanel.style.display = 'none';
            document.getElementById('pr_total').textContent     = '₱—';
            document.getElementById('pr_dpNotice').style.display = 'none';
            state.totalCost  = 0;
            state.packageData = null;
            return;
        }

        const eventDate = new Date(state.date);
        if (isNaN(eventDate.getTime())) return;
        const now = new Date();
        const diffHours = (eventDate - now) / (1000 * 60 * 60);
        const isLastMinute = (diffHours < rushThresholdHours);
        if (pax < minPax) {
            errEl.textContent   = `Minimum of ${minPax} guests is required.`;
            errEl.style.display = 'block';
            badge.style.display = 'none';
            dishPanel.style.display = 'none';
            state.totalCost = 0;
            return;
        }
        if (pax > maxPax) {
            errEl.textContent   = `Maximum capacity is ${maxPax} guests. Please contact management for larger events.`;
            errEl.style.display = 'block';
            badge.style.display = 'none';
            dishPanel.style.display = 'none';
            state.totalCost = 0;
            return;
        }
        errEl.style.display = 'none';

        // ── USER-SELECTED PACKAGE TIER LOGIC ────────────────
        // Get all tiers for the selected package and sort by pax_count ASC
        const tiers = allPackages.filter(p => p.set_name === selectedSetName)
                                 .sort((a,b) => parseInt(a.pax_count) - parseInt(b.pax_count));
        
        if (tiers.length === 0) return;

        // Find highest tier <= pax
        let pkg = tiers.reduce((prev, curr) => {
            return (parseInt(curr.pax_count) <= pax) ? curr : prev;
        }, tiers[0]);
        
        let tierPax = parseInt(pkg.pax_count);

        const basePax    = parseInt(pkg.pax_count);
        const basePrice  = parseFloat(pkg.price);
        const ratePerPax = basePrice / basePax;
        const extraPax   = Math.max(0, pax - tierPax);
        const extraCost  = Math.round(extraPax * ratePerPax * 100) / 100;
        let total        = Math.round((basePrice + extraCost + state.transportFee) * 100) / 100;
        const perPax     = Math.round((total / pax) * 100) / 100;

        state.packageId   = pkg.id;
        state.packageData = pkg;
        state.tierPax     = tierPax;
        state.basePrice    = basePrice;
        state.extraPax    = extraPax;
        state.extraPax    = extraPax;
        state.extraCost   = extraCost;
        state.totalCost   = total;
        
        // Update dish limits dynamically from package or dynamic fallback
        state.maxMain = parseInt(pkg.max_main_dishes) || defaultMaxMain;
        state.maxDessert = parseInt(pkg.max_desserts) || defaultMaxDessert;
        state.maxRice = defaultMaxRice;

        // Dynamic Surcharge Logic
        const inclMain = state.maxMain;
        const inclDessert = state.maxDessert;
        const inclRice = state.maxRice;

        const getSurcharge = (selectedIds, limit, fallbackRate) => {
            let sur = 0;
            selectedIds.forEach((id, index) => {
                const dish = allDishes.find(d => d.id == id);
                if (!dish) return;
                const fee = parseFloat(dish.custom_fee) || 0;
                const isExtra = index >= limit;

                // Only charge when the dish EXCEEDS the free allowance (limit).
                // Dishes within the limit are always free, even if they have a custom_fee.
                if (isExtra) {
                    sur += fee;
                }
            });
            return sur * pax;
        };

        const surchargeMain = getSurcharge(state.selectedMain, inclMain, parseFloat(state.additionalRates.main) || defaultRateMain);
        const surchargeDessert = getSurcharge(state.selectedDesserts, inclDessert, parseFloat(state.additionalRates.dessert) || defaultRateDessert);
        const surchargeRice = getSurcharge(state.selectedAdditional, inclRice, parseFloat(state.additionalRates.rice) || defaultRateRice);

        const extraDishSurcharge = surchargeMain + surchargeDessert + surchargeRice;

        // ── Badge ───────────────────────────────────────────────
        document.getElementById('s3_pkgLabel').textContent =
            `${pkg.set_name} — ${tierPax} pax · ₱${basePrice.toLocaleString()}`;
        document.getElementById('s3_pkgBadge').style.display = 'block';

        // Update inclusions — render as pill badges
        const inclBox  = document.getElementById('s3_inclusionsBox');
        const inclList = document.getElementById('s3_inclusionsList');
        if (inclBox && inclList) {
            const defaultItems = [
                `Up to ${pkg.max_main_dishes || defaultMaxMain} Main Dishes`,
                `Up to ${pkg.max_desserts || defaultMaxDessert} Dessert${(pkg.max_desserts || defaultMaxDessert) > 1 ? 's' : ''}`,
                pkg.includes_rice == 1 ? 'Unlimited Rice' : null,
                'Uniformed Servers',
                'Complete Setup & Cleanup',
            ].filter(Boolean);

            const customLines = (pkg.inclusions || '')
                .split('\n')
                .map(l => l.trim())
                .filter(l => l.length > 0);

            inclList.innerHTML = [...customLines, ...defaultItems].map(l =>
                `<span style="display:inline-flex; align-items:center; gap:5px; background:rgba(48,209,88,0.1); color:#1A7A32;
                             border:0.5px solid rgba(48,209,88,0.25); border-radius:20px; padding:4px 12px;
                             font-size:11.5px; font-weight:600; white-space:nowrap;">
                    <i class="fas fa-circle-check" style="font-size:10px;"></i>${esc(l)}
                 </span>`
            ).join('');
            inclBox.style.display = 'block';
        }

        // ── Compute final totals ──────────────────────────────────
        state.customFees = extraDishSurcharge;

        const addOnSum = state.customItems.reduce((acc, item) => {
            const p = parseFloat(item.price) || 0;
            return acc + ((item.category === 'main' || item.category === 'dessert') ? p * state.pax : p);
        }, 0);
        const totalSurcharge = extraDishSurcharge + addOnSum;

        total = Math.round((basePrice + extraCost + totalSurcharge + state.transportFee) * 100) / 100;
        const finalPerPax = Math.round((total / pax) * 100) / 100;
        state.totalCost = total;

        const dpPct = isLastMinute ? rushDpPct : minDpPct;
        const minDP = Math.ceil(total * dpPct);

        // ── Single render call — all DOM writes go here ──────────
        renderPriceCard({
            pkg, tierPax, basePrice, ratePerPax,
            extraPax, extraCost,
            transportFee: state.transportFee,
            totalSurcharge,
            total, finalPerPax,
            minDP, dpPct, isLastMinute,
        });

        updateDishCounters();
        dishPanel.style.display = 'block';

        const dpEl = document.getElementById('s4_dp');
        if (!dpEl.value) dpEl.value = minDP.toFixed(2);
        updateSummaryBalances();
    };

    /* ── PRICE CARD RENDERER (single render function — #2 state cleanup) ── */
    function renderPriceCard(s) {
        const el = (id) => document.getElementById(id);
        const fmt = (n) => n.toLocaleString('en-PH', { minimumFractionDigits: 2 });

        // Tier row
        el('pr_tierRow').style.display = 'block';
        el('pr_tierName').textContent  = `${s.pkg.set_name} · ${s.tierPax} pax (base ₱${fmt(s.basePrice)})`;
        el('pr_baseDesc').textContent  = `${s.tierPax} pax × ₱${fmt(s.ratePerPax)}/pax`;
        el('pr_basePrice').textContent = `₱${fmt(s.basePrice)}`;

        // Extra pax row
        el('pr_extraRow').style.display = s.extraPax > 0 ? 'flex' : 'none';
        if (s.extraPax > 0) {
            el('pr_extraDesc').textContent = `${s.extraPax} extra pax × ₱${fmt(s.ratePerPax)}/pax`;
            el('pr_extraCost').textContent = `+₱${fmt(s.extraCost)}`;
        }

        // Transport row
        el('pr_transportRow').style.display = s.transportFee > 0 ? 'flex' : 'none';
        if (s.transportFee > 0) el('pr_transportCost').textContent = `+₱${fmt(s.transportFee)}`;

        // Surcharge row
        el('pr_customFeeRow').style.display = s.totalSurcharge > 0 ? 'flex' : 'none';
        if (s.totalSurcharge > 0) el('pr_customFeeCost').textContent = '+' + Format.peso(s.totalSurcharge);

        // Total & per-pax
        el('pr_total').textContent  = '₱' + fmt(s.total);
        el('pr_perPax').textContent = '₱' + fmt(s.finalPerPax) + '/pax';

        // DP notice
        el('pr_dpNotice').style.display = 'flex';
        el('pr_minDP').textContent = '₱' + fmt(s.minDP);

        const noticeEl  = el('pr_dpNotice');
        const noticeLabel = noticeEl.querySelector('div:first-child');
        if (s.isLastMinute) {
            noticeEl.style.background   = 'rgba(255,59,48,0.08)';
            noticeEl.style.borderColor  = 'rgba(255,59,48,0.2)';
            el('pr_minDP').style.color  = '#C0392B';
            noticeLabel.textContent     = `${Math.round(rushDpPct * 100)}% Payment Required (<${rushThresholdHours}h)`;
            noticeLabel.style.color     = '#C0392B';
        } else {
            noticeEl.style.background   = 'rgba(255,149,0,0.08)';
            noticeEl.style.borderColor  = 'rgba(255,149,0,0.2)';
            el('pr_minDP').style.color  = '#9A5400';
            noticeLabel.textContent     = `Minimum Downpayment (${Math.round(minDpPct * 100)}%)`;
            noticeLabel.style.color     = '#9A5400';
        }
    }

    /* ── DISH SELECTION RENDERER ── */
    function buildDishSelection() {
        // Group categorizing — Main categories first
        const mainCats = ['Beef', 'Pork', 'Chicken', 'Seafood', 'Vegetables', 'Pasta', 'Main'];
        const dessertCats = ['Dessert', 'Desserts', 'Sweets'];
        const riceCats = ['Rice'];
        
        let htmlMains = '';
        let htmlDesserts = '';

        const renderGroup = (cat, items, isMain, isDessert) => {
            if(!items || items.length === 0) return '';
            
            // Filter by Meal Type
            const filtered = items.filter(d => {
                const mt = d.meal_type || 'all';
                const mtList = mt.split(',');
                return mtList.includes('all') || mtList.includes(state.mealType) || state.mealType === 'all';
            });
            if(filtered.length === 0) return '';

            const grid = filtered.map(d => {
                const isSelected = isMain 
                    ? state.selectedMain.includes(d.id) 
                    : isDessert ? state.selectedDesserts.includes(d.id) : state.selectedAdditional.includes(d.id);
                
                // Determine if this specific card should show "Extra" badge
                let isExtra = false;
                if (isSelected) {
                    if (isMain) {
                        const idx = state.selectedMain.indexOf(d.id);
                        if (idx >= state.maxMain) isExtra = true;
                    } else if (isDessert) {
                        const idx = state.selectedDesserts.indexOf(d.id);
                        if (idx >= state.maxDessert) isExtra = true;
                    } else {
                        const idx = state.selectedAdditional.indexOf(d.id);
                        if (idx >= state.maxRice) isExtra = true;
                    }
                }

                return `
                    <div class="dish-card ${isSelected ? 'selected' : ''} ${isExtra ? 'surcharged' : ''}" id="dish_${d.id}" onclick="toggleDish(${d.id}, ${isMain}, ${isDessert})">
                        <div class="dish-icon-container">
                            <span class="dish-emoji">${isMain?'🍲':isDessert?'🍮':'🍚'}</span>
                        </div>
                        <div class="dish-info">
                            <div class="dish-name">${esc(d.name)}</div>
                            <div class="dish-meta">${(d.meal_type || 'all').replace(/,/g, ' · ')}</div>
                        </div>
                    </div>
                `;
            }).join('');
            
            return `
                <div style="margin-bottom:12px;">
                    <div style="font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:rgba(60,60,67,0.4); margin-bottom:6px;">${cat}</div>
                    <div class="dish-grid">${grid}</div>
                </div>
            `;
        };

        let htmlRice = '';
        let htmlOthers = '';

        Object.keys(allDishesGroups).forEach(cat => {
            const items = allDishesGroups[cat];
            const lowerCat = cat.toLowerCase();
            if (mainCats.some(c => c.toLowerCase() === lowerCat)) {
                htmlMains += renderGroup(cat, items, true, false);
            } else if (dessertCats.some(c => c.toLowerCase() === lowerCat)) {
                htmlDesserts += renderGroup(cat, items, false, true);
            } else if (riceCats.some(c => c.toLowerCase() === lowerCat)) {
                htmlRice += renderGroup(cat, items, false, false);
            } else {
                htmlOthers += renderGroup(cat, items, false, false);
            }
        });

        const mainGrid = document.getElementById('mainDishGrid');
        if (mainGrid) mainGrid.innerHTML = htmlMains || '<div style="font-size:12px; color:rgba(60,60,67,0.4); padding:10px;">No main dishes found for this meal type.</div>';
        
        const dessertGrid = document.getElementById('dessertDishGrid');
        if (dessertGrid) dessertGrid.innerHTML = htmlDesserts || '<div style="font-size:12px; color:rgba(60,60,67,0.4); padding:10px;">No desserts found for this meal type.</div>';
        
        const riceGrid = document.getElementById('riceDishGrid');
        if (riceGrid) riceGrid.innerHTML = htmlRice || '<div style="font-size:12px; color:rgba(60,60,67,0.4); padding:10px;">No rice options found for this meal type.</div>';

        const otherGrid = document.getElementById('additionalDishGrid');
        if (otherGrid) otherGrid.innerHTML = htmlOthers;
    }

    window.setManualMealType = function(type) {
        state.mealType = type;
        
        // Update UI buttons
        document.querySelectorAll('.meal-filter-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        const activeBtn = document.getElementById('mf_' + type);
        if (activeBtn) activeBtn.classList.add('active');
        
        buildDishSelection();
    };

    window.toggleDish = function (id, isMain, isDessert) {
        const el = document.getElementById('dish_' + id);
        if (!el) return;
        
        let selectedArr = state.selectedAdditional;
        let limit = 99;
        let limitName = 'additional';

        if (isMain) {
            selectedArr = state.selectedMain;
            limit = state.maxMain;
            limitName = 'main';
        } else if (isDessert) {
            selectedArr = state.selectedDesserts;
            limit = state.maxDessert;
            limitName = 'dessert';
        }

        const idx = selectedArr.indexOf(id);
        if (idx > -1) {
            selectedArr.splice(idx, 1);
        } else {
            selectedArr.push(id);
        }
        
        // Premium Green Theme Re-render
        buildDishSelection();
        calcPricing();
    };

    function updateDishCounters() {
        const mNode = document.getElementById('mainDishCounter');
        if(mNode) mNode.textContent = `${state.selectedMain.length} / ${state.maxMain}`;
        const dNode = document.getElementById('dessertCounter');
        if(dNode) dNode.textContent = `${state.selectedDesserts.length} / ${state.maxDessert}`;
        
        const ml = document.getElementById('maxMainLabel');
        if(ml) ml.textContent = state.maxMain;
        const md = document.getElementById('maxDessertLabel');
        if(md) md.textContent = state.maxDessert;
    }

    /* ── STEP 4 DOWNPAYMENT ── */
    window.onDPInput = function () {
        updateSummaryBalances();
    };

    window.onDPMethodChange = function () {
        const method = document.getElementById('s4_dpMethod').value;
        const refLabel = document.getElementById('s4_dpRefLabel');
        if (method !== 'cash') {
            refLabel.innerHTML = 'Reference No. <span class="text-danger">*</span>';
        } else {
            refLabel.innerHTML = 'Reference No.';
        }
    };

    function updateSummaryBalances() {
        const total  = state.totalCost;
        const dp     = parseFloat(document.getElementById('s4_dp').value) || 0;
        
        const eventDate = new Date(state.date);
        const now       = new Date();
        const diffHours = (eventDate - now) / (1000 * 60 * 60);
        const isLastMinute = diffHours < rushThresholdHours;
        const dpPct        = isLastMinute ? rushDpPct : minDpPct;

        const minDP  = Math.ceil(total * dpPct * 100) / 100;
        const balance = Math.max(0, total - dp);
        const errEl  = document.getElementById('s4_dpError');
        const subBtn = document.getElementById('stepSubmitBtn');

        document.getElementById('s4_total').textContent   = Format.peso(total);
        document.getElementById('s4_minDP').textContent   = Format.peso(minDP);
        document.getElementById('s4_balance').textContent = Format.peso(balance);
        
        const minLabel = isLastMinute ? `${Math.round(rushDpPct * 100)}% Payment Required` : `Minimum Downpayment (${Math.round(minDpPct * 100)}%)`;
        document.querySelector('#s4_minDP').previousElementSibling.textContent = minLabel;
        document.getElementById('dpLabel').querySelector('span').textContent = isLastMinute ? ` — minimum ${Math.round(rushDpPct * 100)}% required` : ` — minimum ${Math.round(minDpPct * 100)}% required`;

        state.dpAmount = dp;

        if (dp < minDP - 0.01) {
            const dpLabel = Math.round((isLastMinute ? rushDpPct : minDpPct) * 100);
            errEl.textContent   = isLastMinute 
                ? `${dpLabel}% payment is required for bookings within ${rushThresholdHours} hours.` 
                : `Minimum downpayment is ${dpLabel}% of total (${Format.peso(minDP)}).`;
            errEl.style.display = 'block';
            subBtn.disabled     = true;
            console.log(`${dpLabel}`);
        } else if (dp > total + 0.01) {
            errEl.textContent   = `Cannot exceed total cost of ${Format.peso(total)}.`;
            errEl.style.display = 'block';
            subBtn.disabled     = true;
        } else {
            errEl.style.display = 'none';
            subBtn.disabled     = !state.termsOk;
        }
    }

    window.onTermsChange = function () {
        const total  = state.totalCost;
        const eventDate = new Date(state.date);
        const now       = new Date();
        const diffHours = (eventDate - now) / (1000 * 60 * 60);
        const isLastMinute = diffHours < rushThresholdHours;
        const dpPct        = isLastMinute ? rushDpPct : minDpPct;

        state.termsOk     = document.getElementById('s4_terms').checked;
        const dp          = parseFloat(document.getElementById('s4_dp').value) || 0;
        const minDP       = Math.ceil(total * dpPct * 100) / 100;
        const dpOk        = (dp >= minDP - 0.01 && dp <= total + 0.01);
        document.getElementById('stepSubmitBtn').disabled = !(state.termsOk && dpOk);
    };

    /* ── SUMMARY CARD ───────────────────────────────────────────── */
    function buildSummary() {
        const clientEl   = document.getElementById('s2_client');
        const clientName = state.isNewClient
            ? document.getElementById('nc_name').value
            : (clientEl.options[clientEl.selectedIndex]?.text || '—');

        const pkg = state.packageData;
        const pkgLabel = pkg
            ? `${pkg.set_name} (${state.tierPax} pax base · ₱${parseFloat(pkg.price).toLocaleString()})`
            : '—';

        // Dish names
        const findDish = id => {
            const d = allDishes.find(x => x.id == id);
            return d ? d.name : '';
        };
        const mainNames = state.selectedMain.map(findDish).filter(Boolean);
        const dessertNames = state.selectedDesserts.map(findDish).filter(Boolean);
        const additionalNames = state.selectedAdditional.map(findDish).filter(Boolean);

        document.getElementById('summaryCard').innerHTML = `
            <div style="display:grid; gap:16px;">
                <!-- Section: Event Details -->
                <div style="background:rgba(60,60,67,0.03); border-radius:18px; padding:16px; border:0.5px solid rgba(60,60,67,0.08);">
                    <div style="font-size:10px; font-weight:800; color:var(--label-3); text-transform:uppercase; letter-spacing:0.8px; margin-bottom:10px;">📅 Event Details</div>
                    <div style="display:grid; gap:8px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px;"><span style="color:var(--label-2);">Date & Time</span><strong>${Format.dateShort(state.date)} · ${state.time ? Format.time(state.time) : ''}</strong></div>
                        <div style="display:flex; justify-content:space-between; font-size:13px;"><span style="color:var(--label-2);">Meal Type</span><strong style="text-transform:capitalize; color:var(--sys-green-dark);">${state.mealType || 'all'}</strong></div>
                        ${state.location ? `<div style="display:flex; justify-content:space-between; font-size:13px;"><span style="color:var(--label-2);">Venue</span><strong style="text-align:right; max-width:180px;">${state.location}</strong></div>` : ''}
                        <div style="display:flex; justify-content:space-between; font-size:13px;"><span style="color:var(--label-2);">Client</span><strong>${clientName}</strong></div>
                    </div>
                </div>

                <!-- Section: Package & Pricing -->
                <div style="background:rgba(48,209,88,0.03); border-radius:18px; padding:16px; border:0.5px solid rgba(48,209,88,0.15);">
                    <div style="font-size:10px; font-weight:800; color:#1A7A32; text-transform:uppercase; letter-spacing:0.8px; margin-bottom:10px;">💰 Investment Summary</div>
                    <div style="display:grid; gap:8px; margin-bottom:12px;">
                        <div style="display:flex; justify-content:space-between; font-size:13px;"><span style="color:var(--label-2);">Package Tier</span><strong>${pkgLabel}</strong></div>
                        <div style="display:flex; justify-content:space-between; font-size:13px;"><span style="color:var(--label-2);">Guest Count</span><strong>${state.pax} pax</strong></div>
                        <div style="display:flex; justify-content:space-between; font-size:13px;"><span style="color:var(--label-2);">Base Price</span><strong>${Format.peso(state.basePrice)}</strong></div>
                        ${state.extraPax > 0 ? `<div style="display:flex; justify-content:space-between; font-size:13px;"><span style="color:var(--label-2);">Extra ${state.extraPax} pax</span><strong style="color:var(--sys-orange);">+${Format.peso(state.extraCost)}</strong></div>` : ''}
                        ${(state.transportFee > 0) ? `<div style="display:flex; justify-content:space-between; font-size:13px;"><span style="color:var(--label-2);">Transport Fee</span><strong>+${Format.peso(state.transportFee)}</strong></div>` : ''}
                        ${(state.customFees + state.customItems.reduce((a,b)=>a+(parseFloat(b.price)||0),0)) > 0 ? `
                            <div style="display:flex; justify-content:space-between; font-size:13px;">
                                <span style="color:var(--label-2);">Menu & Add-on Surcharges</span>
                                <strong style="color:var(--sys-red);">+${Format.peso(state.customFees + state.customItems.reduce((a,b)=>a+(parseFloat(b.price)||0),0))}</strong>
                            </div>` : ''}
                    </div>
                    <div style="height:1px; background:rgba(48,209,88,0.1); margin-bottom:10px;"></div>
                    <div style="display:flex; justify-content:space-between; align-items:baseline;">
                        <span style="font-weight:800; font-size:14px;">Total Cost</span>
                        <strong style="font-size:22px; color:var(--sys-green-deeper); letter-spacing:-0.5px;">${Format.peso(state.totalCost)}</strong>
                    </div>
                </div>

                <!-- Section: Menu Selection -->
                <div style="background:white; border-radius:18px; padding:16px; border:0.5px solid rgba(60,60,67,0.08); box-shadow:0 4px 12px rgba(0,0,0,0.03);">
                    <div style="font-size:10px; font-weight:800; color:var(--label-3); text-transform:uppercase; letter-spacing:0.8px; margin-bottom:12px;">🍽️ Menu Selection</div>
                    
                    <div style="display:grid; gap:12px;">
                        ${mainNames.length > 0 ? `
                        <div>
                            <div style="font-size:11px; font-weight:700; color:var(--label-2); margin-bottom:6px;">Main Courses (${mainNames.length})</div>
                            <div style="display:flex; flex-wrap:wrap; gap:6px;">
                                ${mainNames.map(n => `<span style="background:rgba(48,209,88,0.1); color:#1A7A32; border-radius:8px; padding:4px 10px; font-size:11.5px; font-weight:600; border:0.5px solid rgba(48,209,88,0.15);">${n}</span>`).join('')}
                            </div>
                        </div>` : ''}
                        
                        ${dessertNames.length > 0 ? `
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; font-size:13px;">
                            <span style="color:var(--label-2); min-width:80px;">Desserts</span>
                            <strong style="text-align:right; font-weight:600;">${dessertNames.join(', ')}</strong>
                        </div>` : ''}
                        
                        ${additionalNames.length > 0 ? `
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; font-size:13px;">
                            <span style="color:var(--label-2); min-width:80px;">Rice & Others</span>
                            <strong style="text-align:right; font-weight:600;">${additionalNames.join(', ')}</strong>
                        </div>` : ''}

                        ${state.customItems.length > 0 ? `
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; font-size:13px;">
                            <span style="color:var(--label-2); min-width:80px;">Custom Add-ons</span>
                            <strong style="text-align:right; font-weight:600; color:var(--sys-indigo);">${state.customItems.map(c => c.name).filter(Boolean).join(', ')}</strong>
                        </div>` : ''}
                    </div>
                </div>

                ${state.dietaryNotes ? `
                <div style="background:rgba(255,149,0,0.07); border:0.5px solid rgba(255,149,0,0.3); border-radius:18px; padding:14px 16px;">
                    <div style="font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.8px; color:#9A5400; margin-bottom:6px;">⚠️ Dietary / Allergy Notes</div>
                    <div style="font-size:13px; color:rgba(60,60,67,0.8); line-height:1.6; font-weight:500;">${state.dietaryNotes}</div>
                </div>` : ''}
            </div>
        `;

        const eventDate = new Date(state.date);
        const now       = new Date();
        const diffHours = (eventDate - now) / (1000 * 60 * 60);
        const isLastMinute = diffHours < rushThresholdHours;
        const dpPct        = isLastMinute ? rushDpPct : minDpPct;

        const dpEl = document.getElementById('s4_dp');
        if (!dpEl.value) dpEl.value = Math.ceil(state.totalCost * dpPct).toFixed(2);
        updateSummaryBalances();
        document.getElementById('stepSubmitBtn').disabled = true;
    }

    /* ── CUSTOM ADD-ONS ── */
    window.addCustomItemRow = function () {
        const id = Date.now();
        state.customItems.push({
            id: id,
            name: '',
            category: 'other',
            price: 0,
            notes: ''
        });
        renderCustomItems();
        calcPricing();
    };

    window.removeCustomItem = function (id) {
        state.customItems = state.customItems.filter(x => x.id !== id);
        renderCustomItems();
        calcPricing();
    };

    window.onCustomItemChange = function (id, field, value) {
        const item = state.customItems.find(x => x.id === id);
        if (item) {
            item[field] = value;
            if (field === 'price') calcPricing();
        }
    };

    function renderCustomItems() {
        const container = document.getElementById('customItemsContainer');
        const hint = document.getElementById('noCustomItemsHint');
        
        container.innerHTML = '';
        if (state.customItems.length === 0) {
            hint.style.display = 'block';
            return;
        }
        hint.style.display = 'none';

        state.customItems.forEach(item => {
            const div = document.createElement('div');
            div.style = 'display:grid; grid-template-columns:1fr 100px 100px 40px; gap:8px; align-items:center; background:white; padding:10px; border:0.5px solid rgba(60,60,67,0.1); border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,0.02);';
            div.innerHTML = `
                <input type="text" class="form-control form-control-sm" placeholder="Item name (e.g. Lechon Belly)" value="${esc(item.name)}" oninput="onCustomItemChange(${item.id}, 'name', this.value)" title="Name of the off-menu item">
                <select class="form-control form-control-sm" onchange="onCustomItemChange(${item.id}, 'category', this.value)" title="Pricing category">
                    <option value="main" ${item.category==='main'?'selected':''}>Main</option>
                    <option value="dessert" ${item.category==='dessert'?'selected':''}>Dessert</option>
                    <option value="other" ${item.category==='other'?'selected':''}>Other</option>
                </select>
                <div class="input-group input-group-sm">
                    <span class="input-prefix" style="font-size:10px;">₱</span>
                    <input type="text" class="form-control form-control-sm" value="${item.price}" oninput="onCustomItemChange(${item.id}, 'price', this.value)" placeholder="Price" data-restrict="price" title="Cost of this custom item">
                </div>
                <button type="button" class="btn btn-sm btn-link text-danger" onclick="removeCustomItem(${item.id})" title="Remove custom item"><i class="fas fa-trash-alt"></i></button>
            `;
            container.appendChild(div);
            // Re-apply restrictions to the new element
            div.querySelectorAll('[data-restrict]').forEach(el => Form.restrictInput(el, el.dataset.restrict));
        });
    }

    /* ── SUBMIT ─────────────────────────────────────────────── */
    window.submitBooking = async function () {
        const btn = document.getElementById('stepSubmitBtn');
        if (!state.termsOk) { Toast.error('Please agree to the Terms & Conditions.'); return; }

        const total = state.totalCost;
        const eventDate = new Date(state.date);
        const now       = new Date();
        const diffHours = (eventDate - now) / (1000 * 60 * 60);
        const isLastMinute = diffHours < rushThresholdHours;
        const dpPct        = isLastMinute ? rushDpPct : minDpPct;

        const dpAmt = parseFloat(document.getElementById('s4_dp').value) || 0;
        const minDP = Math.ceil(total * dpPct * 100) / 100;
        if (dpAmt < minDP - 0.01) {
            const rushDays = Math.round(rushThresholdHours / 24);
            Toast.error(isLastMinute ? `${Math.round(rushDpPct * 100)}% payment is required for bookings made within ${rushDays} days (${rushThresholdHours}h).` : `Downpayment is below the ${Math.round(minDpPct * 100)}% minimum.`); 
            return;
        }

        const allSelectedDishes = [
            ...state.selectedMain,
            ...state.selectedDesserts,
            ...state.selectedAdditional
        ];

        Form.setLoading(btn, true);
        try {
            const payload = {
                client_id:          state.clientId,
                nc_name:            state.isNewClient ? document.getElementById('nc_name').value : null,
                nc_phone:           state.isNewClient ? document.getElementById('nc_phone').value : null,
                nc_email:           state.isNewClient ? document.getElementById('nc_email').value : null,
                nc_address:         state.isNewClient ? document.getElementById('nc_address').value : null,
                nc_messenger:       state.isNewClient ? document.getElementById('nc_messenger').value : null,
                package_id:         state.packageId,
                event_date:         state.date,
                event_time:         state.time        || null,
                event_type:         state.eventType   || 'Wedding',
                event_location:     state.location    || null,
                transport_fee:      state.transportFee || 0,
                pax_count:          state.pax,
                booking_status:     'confirmed',
                notes:              state.notes        || null,
                dietary_notes:      state.dietaryNotes || null,
                selected_dishes:    allSelectedDishes,
                custom_items:       state.customItems, // {name, category, price, notes}
                downpayment:        dpAmt > 0 ? dpAmt : null,
                downpayment_method: document.getElementById('s4_dpMethod').value,
                downpayment_ref:    document.getElementById('s4_dpRef').value || null,
            };

            const res = await Api.post(BASE + 'src/api/bookings.php', payload);
            const isConfirmed = res.booking_status === 'confirmed';

            // Staff assignment removed — handled via Dispatching module after booking

            if (isConfirmed) {
                Toast.success(
                    `${res.package_name} booking confirmed! Downpayment of ${Format.peso(dpAmt)} recorded.`,
                    6000
                );
            } else {
                const eventDate = new Date(state.date);
                const now       = new Date();
                const diffHours = (eventDate - now) / (1000 * 60 * 60);
                const isLastMinute = diffHours < rushThresholdHours;
                const dpPct        = isLastMinute ? rushDpPct : minDpPct;

                Toast.warning(
                    `${res.package_name} booking saved as Pending. ` +
                    `Record a payment of at least ${Format.peso(res.total_cost * dpPct)} in Financials to confirm it.`,
                    8000
                );
            }
            Modal.close('bookingStepperModal');
            if (typeof loadBookings   === 'function') await loadBookings();
            if (typeof loadBookingsFD === 'function') await loadBookingsFD();
        } catch(e) {
            Toast.error(e.message);
        }
        Form.setLoading(btn, false);
    };

})();
</script>
