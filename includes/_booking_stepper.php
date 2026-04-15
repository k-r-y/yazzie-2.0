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
?>

<!-- ╔══════════════════════════════════════════════════════════════╗
     ║   4-STEP BOOKING STEPPER MODAL                             ║
     ╚══════════════════════════════════════════════════════════════╝ -->
<div class="modal fade" id="bookingStepperModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content" style="border-radius:24px; overflow:hidden;">

            <!-- ── HEADER ── -->
            <div class="modal-header" style="padding:20px 28px; border-bottom:0.5px solid rgba(60,60,67,0.1);">
                <div>
                    <h5 class="modal-title" style="font-size:17px; font-weight:800; letter-spacing:-0.4px;">
                        <i class="fas fa-calendar-plus me-2" style="color:var(--sys-green);"></i>New Event Booking
                    </h5>
                    <div id="stepperSubtitle" style="font-size:12px; color:rgba(60,60,67,0.45); margin-top:2px;">Step 1 of 4</div>
                </div>
                <button type="button" class="btn-close" onclick="closeBookingStepper()"></button>
            </div>

            <!-- ── STEP PROGRESS NAV ── -->
            <div style="padding:20px 28px 0; background:rgba(242,242,247,0.5); border-bottom:0.5px solid rgba(60,60,67,0.08);">
                <div id="stepNav" style="display:flex; align-items:center; gap:0; margin-bottom:0;">

                    <?php
                    $steps = [
                        ['icon'=>'fa-calendar-day',  'label'=>'Date'],
                        ['icon'=>'fa-user',          'label'=>'Client'],
                        ['icon'=>'fa-id-badge',      'label'=>'Staff'],
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
                    <?php if ($n < 5): ?>
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
                                <label class="form-label">Event Date <span class="required">*</span></label>
                                <input type="date" class="form-control" id="s1_date"
                                       min="<?= date('Y-m-d', strtotime('+3 days')) ?>" oninput="checkAvailability()">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Event Time <span class="required">*</span></label>
                                <input type="time" class="form-control" id="s1_time" min="08:00" max="21:59" onchange="let h = this.value ? parseInt(this.value.split(':')[0]) : 0; if(this.value && (h < 8 || h >= 22)) { Toast.error('Operating hours are from 08:00 AM to 09:59 PM.'); this.value=''; }">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Event Venue / Location <span class="required">*</span></label>
                            <input type="text" class="form-control" id="s1_location" placeholder="Full address of the venue…" required>
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
                                    <label class="form-label">Full Name <span class="required">*</span></label>
                                    <input type="text" class="form-control" id="nc_name" placeholder="Maria Santos">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Phone Number <span class="required">*</span></label>
                                    <input type="tel" class="form-control" id="nc_phone" placeholder="09XXXXXXXXX" pattern="\d*" maxlength="11" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Email Address <span class="required">*</span></label>
                                    <input type="email" class="form-control" id="nc_email" placeholder="email@example.com" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Address</label>
                                    <input type="text" class="form-control" id="nc_address" placeholder="City, Province">
                                </div>
                            </div>
                        </div>



                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Event Notes (Optional)</label>
                            <textarea class="form-control" id="s2_notes" rows="2" placeholder="e.g. VIP guest attending..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- ══ STEP 3: Staff Assignment ══ -->
                <div class="stepper-panel" id="panel3">
                    <div style="max-width:650px; margin:0 auto;">
                        <h6 style="font-size:15px; font-weight:700; margin-bottom:4px;">🧑‍🍳 Staff Allocation</h6>
                        <p style="font-size:13px; color:rgba(60,60,67,0.5); margin-bottom:14px;">Select the crew for this event. 
                        Guest count: <b id="s3_paxCount">0</b>. Minimum staff needed: <b id="s3_minStaffCount">0</b>.</p>

                        <div class="form-group">
                            <label class="form-label">How many guests are expected? <span class="required">*</span></label>
                            <input class="form-control" type="number" id="s3_paxInput" min="50" max="300" step="1" value="50" oninput="updateStaffMin()">
                        </div>

                        <div style="background:var(--surface-2); border-radius:12px; padding:16px; margin-bottom:16px; border:1px solid var(--border);">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                                <div style="font-weight:700; font-size:14px;">Staff Selection <span class="required">*</span></div>
                                <div id="s3_counter" style="font-size:12px; font-weight:700; background:rgba(255,59,48,0.1); color:#FF3B30; padding:4px 12px; border-radius:99px;">0 / M</div>
                            </div>
                            <!-- Staff list injected here -->
                            <div id="staffSelectBox" style="max-height:240px; overflow-y:auto; padding-right:6px;">
                                <div class="spinner"></div>
                            </div>
                        </div>
                    </div>
                </div>

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

                            <div class="form-group" style="margin-bottom:6px; display:none;">
                                <label class="form-label" style="font-size:13px; font-weight:700;">
                                    Number of Guests (Pax) <span class="required">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-prefix" style="font-weight:700;">👥</span>
                                    <input type="number" class="form-control" id="s3_pax"
                                           min="50" step="1" placeholder="e.g. 75"
                                           style="font-size:20px; font-weight:700; letter-spacing:-0.3px;"
                                           oninput="calcPricing()">
                                </div>
                                <div style="font-size:11.5px; color:rgba(60,60,67,0.4); margin-top:4px;">
                                    Minimum 50 guests &middot; Packages increment every 50 pax
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
                                <div style="font-size:11px; font-weight:700; color:#9A5400; margin-bottom:2px;">Minimum Downpayment (50%)</div>
                                <div id="pr_minDP" style="font-size:15px; font-weight:800; color:#9A5400;"></div>
                            </div>
                        </div>

                    </div>

                    <!-- Row 2: Dish Selection (full width) -->
                    <div id="dishSelectionPanel" style="display:none; padding-top:20px; border-top:0.5px solid rgba(60,60,67,0.08);">

                        <!-- Main Dishes -->
                        <div style="margin-bottom:20px;">
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                                <div>
                                    <div style="font-size:13px; font-weight:700;">Main Dishes</div>
                                    <div style="font-size:11.5px; color:rgba(60,60,67,0.45);">Choose up to <span id="maxMainLabel">5</span> dishes</div>
                                </div>
                                <div id="mainDishCounter" style="font-size:12px; font-weight:700; background:rgba(48,209,88,0.1); color:#1A7A32; padding:4px 12px; border-radius:99px;">
                                    0 / 5
                                </div>
                            </div>
                            <div id="mainDishGrid" class="dish-grid"></div>
                        </div>

                        <!-- Dessert -->
                        <div style="margin-bottom:16px;">
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                                <div>
                                    <div style="font-size:13px; font-weight:700;">Dessert</div>
                                    <div style="font-size:11.5px; color:rgba(60,60,67,0.45);">Choose up to <span id="maxDessertLabel">1</span> dish(es)</div>
                                </div>
                                <div id="dessertCounter" style="font-size:12px; font-weight:700; background:rgba(255,149,0,0.1); color:#9A5400; padding:4px 12px; border-radius:99px;">
                                    0 / 1
                                </div>
                            </div>
                            <div id="dessertDishGrid" class="dish-grid"></div>
                        </div>

                        <!-- Rice tag -->
                        <div style="display:flex; align-items:center; gap:8px; padding:8px 14px; background:rgba(242,242,247,0.8); border-radius:10px; width:fit-content;">
                            <span style="font-size:14px;">🍚</span>
                            <span style="font-size:12px; font-weight:600; color:rgba(60,60,67,0.65);">Steamed Rice &mdash; Always included</span>
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
                                    <span style="font-size:13px; color:rgba(60,60,67,0.6);">Minimum Downpayment (50%)</span>
                                    <span style="font-weight:700; color:#9A5400;" id="s4_minDP">—</span>
                                </div>
                                <div style="height:0.5px; background:rgba(60,60,67,0.1); margin:10px 0;"></div>
                                <div style="display:flex; justify-content:space-between;">
                                    <span style="font-size:13px; font-weight:700;">Remaining Balance</span>
                                    <span style="font-size:16px; font-weight:800; color:#C0392B;" id="s4_balance">—</span>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Downpayment Amount (₱)
                                    <span style="font-size:11px; font-weight:400; color:rgba(60,60,67,0.4);"> — minimum 50% required</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-prefix">₱</span>
                                    <input type="number" class="form-control" id="s4_dp"
                                           min="0" step="0.01" placeholder="0.00"
                                           oninput="onDPInput()">
                                </div>
                                <div id="s4_dpError" style="font-size:11.5px; color:#C0392B; margin-top:4px; display:none;"></div>
                            </div>

                            <div class="form-grid-2" style="gap:10px;">
                                <div class="form-group">
                                    <label class="form-label">Payment Method</label>
                                    <select class="form-control" id="s4_dpMethod">
                                        <option value="cash">💵 Cash</option>
                                        <option value="gcash">📱 GCash</option>
                                        <option value="maya">📱 Maya</option>
                                        <option value="bank_transfer">🏦 Bank Transfer</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Reference No.</label>
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
                                    The client acknowledges full liability for any missing, damaged, or unreturned equipment and supplies provided by Yazzies Catering. A minimum downpayment of <strong>50%</strong> is required to confirm the booking. The remaining balance must be settled on or before the event date.
                                </p>
                                <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                    <input type="checkbox" id="s4_terms" onchange="onTermsChange()"
                                           style="width:16px; height:16px; accent-color:var(--sys-green);">
                                    <span style="font-size:12px; font-weight:600; color:rgba(0,0,0,0.75);">
                                        I have read and agree to the Terms & Conditions.
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /modal-body -->

            <!-- ── FOOTER NAVIGATION ── -->
            <div class="modal-footer" style="padding:16px 28px; border-top:0.5px solid rgba(60,60,67,0.08); gap:10px;">
                <button class="btn btn-secondary" id="stepPrevBtn" onclick="stepGo(-1)" style="display:none;">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <div style="flex:1;"></div>
                <button class="btn btn-outline-secondary" onclick="closeBookingStepper()">Cancel</button>
                <button class="btn btn-primary" id="stepNextBtn" onclick="stepGo(1)">
                    Next <i class="fas fa-arrow-right"></i>
                </button>
                <button class="btn btn-success" id="stepSubmitBtn" style="display:none;" onclick="submitBooking()">
                    <i class="fas fa-check-circle"></i> Confirm Booking
                </button>
            </div>

        </div>
    </div>
</div>

<!-- ══ STEPPER STYLES ══ -->
<style>
.stepper-step .stepper-circle {
    background: rgba(120,120,128,0.1);
    color: rgba(60,60,67,0.4);
    border: 1.5px solid rgba(60,60,67,0.15);
}
.stepper-step > div:last-child { color: rgba(60,60,67,0.35); }
.stepper-step.active .stepper-circle {
    background: var(--sys-green);
    color: #fff;
    border-color: var(--sys-green);
    box-shadow: 0 4px 12px rgba(48,209,88,0.28);
}
.stepper-step.active > div:last-child { color: var(--sys-green-dark); }
.stepper-step.done .stepper-circle {
    background: rgba(48,209,88,0.12);
    color: var(--sys-green-dark);
    border-color: rgba(48,209,88,0.3);
}
.stepper-step.done > div:last-child { color: rgba(60,60,67,0.5); }
.stepper-line.done { background: rgba(48,209,88,0.4) !important; }

.stepper-panel { display: none; }
.stepper-panel.active { display: block; }

/* Availability badges */
.avail-ok  { display:flex; align-items:center; gap:10px; padding:12px 16px; background:rgba(48,209,88,0.08); border:0.5px solid rgba(48,209,88,0.25); border-radius:12px; font-size:13px; font-weight:600; color:#1A7A32; }
.avail-no  { display:flex; align-items:center; gap:10px; padding:12px 16px; background:rgba(255,59,48,0.07); border:0.5px solid rgba(255,59,48,0.2); border-radius:12px; font-size:13px; font-weight:600; color:#C0392B; }
.avail-chk { display:flex; align-items:center; gap:10px; padding:12px 16px; background:rgba(255,149,0,0.07); border:0.5px solid rgba(255,149,0,0.2); border-radius:12px; font-size:13px; color:#9A5400; }

/* Dish selection grid */
.dish-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 7px;
}
.dish-card {
    position: relative;
    border: 1px solid rgba(60,60,67,0.13);
    border-radius: 10px;
    padding: 8px 10px;
    cursor: pointer;
    transition: all 0.15s;
    background: rgba(255,255,255,0.7);
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 12px;
    font-weight: 500;
    color: rgba(60,60,67,0.75);
    user-select: none;
}
.dish-card:hover {
    border-color: rgba(48,209,88,0.35);
    background: rgba(48,209,88,0.04);
}
.dish-card.selected {
    border-color: var(--sys-green);
    background: rgba(48,209,88,0.08);
    color: #1A7A32;
    font-weight: 600;
}
.dish-card.selected::after {
    content: '\2713';
    position: absolute;
    top: 4px; right: 7px;
    font-size: 10px;
    font-weight: 800;
    color: var(--sys-green);
}
.dish-card.dessert-selected {
    border-color: #FF9500;
    background: rgba(255,149,0,0.07);
    color: #9A5400;
    font-weight: 600;
}
.dish-card.dessert-selected::after {
    content: '\2713';
    position: absolute;
    top: 4px; right: 7px;
    font-size: 10px;
    font-weight: 800;
    color: #FF9500;
}
.dish-card.disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

@media (max-width:768px) {
    #panel4 > div:first-child, #panel5 > div:first-child { grid-template-columns: 1fr !important; }
    .dish-grid { grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); }
}
</style>
<!-- ══ TERMS AND CONDITIONS MODAL ══ -->
<div class="modal-overlay" id="termsModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Terms and Conditions</h3>
            <button class="modal-close" onclick="Modal.close('termsModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="padding:24px; max-height:60vh; overflow-y:auto; font-size:13px; color:#444; line-height:1.6;">
            <p style="margin-bottom:12px;"><strong>1. Reservation & Downpayment</strong><br>A non-refundable 50% downpayment is required to officially lock in the date. The remaining balance must be settled on or before the event date.</p>
            <p style="margin-bottom:12px;"><strong>2. Cancellations</strong><br>Any cancellations made less than 7 days prior to the event will forfeit the entire downpayment to cover material preparations.</p>
            <p style="margin-bottom:12px;"><strong>3. Time Exceedance</strong><br>Standard staffing and catering services run for a maximum of 4 hours. Extensions are subject to an hourly charge.</p>
            <p style="margin-bottom:12px;"><strong>4. Venue Regulations</strong><br>The client is responsible for acquiring all necessary permits and clearances required by the event venue.</p>
            <p style="margin-bottom:12px;"><strong>5. Food Safety</strong><br>Remaining food will be packed safely; however, we will not be held liable for any foodborne illnesses resulting from mishandling or delayed consumption after handover.</p>
        </div>
        <div class="modal-footer" style="justify-content:center; padding:16px;">
            <button class="btn btn-primary" onclick="Modal.close('termsModal')" style="width:100%;">I Understand</button>
        </div>
    </div>
</div>

<!-- ══ STEPPER JAVASCRIPT ══ -->
<script>
(function () {
    /* ── STATE ────────────────────────────────────────────────── */
    const state = {
        step:           1,
        date:           '',
        time:           '',
        location:       '',
        available:      false,
        clientId:       null,
        isNewClient:    false,
        status:         'confirmed',
        notes:          '',
        // Package / pricing
        packageId:      null,
        packageData:    null,
        pax:            0,
        tierPax:        50,
        totalCost:      0,
        basePrice:       0,
        extraPax:       0,
        extraCost:      0,
        // Dish selection
        selectedMain:   [],   // array of dish IDs
        selectedDesserts: [], // array of dish IDs
        maxMain:        5,
        maxDessert:     1,
        // Payment
        dpAmount:       0,
        dpMethod:       'cash',
        dpRef:          '',
        termsOk:        false,
    };

    let allPackages = [];
    let allDishes   = { mainDishes: [], desserts: [] };
    let allClients  = [];
    let availTimer  = null;
    let requiredStaffCount = 5;
    let availableStaffList = [];

    /* ── INIT ────────────────────────────────────────────────── */
    window.openBookingStepper = async function () {
        resetStepper();
        try {
            const [pkgData, dishData, cdData] = await Promise.all([
                Api.get(BASE + '/src/api/packages.php'),
                Api.get(BASE + '/src/api/packages.php', { dishes: 1 }),
                Api.get(BASE + '/src/api/clients.php'),
            ]);
            allPackages          = pkgData.packages   || [];
            allDishes.mainDishes = dishData.mainDishes || [];
            allDishes.desserts   = dishData.desserts   || [];
            allClients           = cdData.clients      || [];
            buildClientSelect();
            buildDishSelection(); // pre-render dish grid (hidden until pax entered)
        } catch(e) { Toast.error('Failed to load form data.'); }
        Modal.open('bookingStepperModal');
    };

    window.closeBookingStepper = function () {
        Modal.close('bookingStepperModal');
    };

    function resetStepper() {
        Object.assign(state, {
            step:1, date:'', time:'', location:'',
            available:false, clientId:null, isNewClient:false,
            status:'confirmed', notes:'',
            packageId:null, packageData:null,
            pax:0, tierPax:50, totalCost:0, basePrice:0, extraPax:0, extraCost:0,
            selectedMain:[], selectedDessert:null, maxMain:5,
            dpAmount:0, dpMethod:'cash', dpRef:'', termsOk:false
        });
        document.getElementById('s1_date').value     = '';
        document.getElementById('s1_time').value     = '';
        document.getElementById('s1_location').value = '';
        document.getElementById('s2_notes').value    = '';
        document.getElementById('s3_pax').value      = '';
        document.getElementById('s4_dp').value       = '';
        document.getElementById('s4_terms').checked  = false;
        document.getElementById('s3_pkgBadge').style.display   = 'none';
        document.getElementById('pr_tierRow').style.display    = 'none';
        document.getElementById('pr_dpNotice').style.display   = 'none';
        document.getElementById('pr_extraRow').style.display   = 'none';
        document.getElementById('pr_total').textContent        = '₱—';
        document.getElementById('dishSelectionPanel').style.display = 'none';
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
        const total = 5;
        state.step = Math.max(1, Math.min(total, n));

        // Update panels
        document.querySelectorAll('.stepper-panel').forEach((p, i) => {
            p.classList.toggle('active', i + 1 === state.step);
        });

        // Update nav circles
        for (let i = 1; i <= total; i++) {
            const el = document.getElementById('stepNav' + i);
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
        if (state.step === 3) fetchAvailableStaff();
        if (state.step === 4) {
             document.getElementById('s3_pax').value = state.pax;
             refreshPackageCalc(); 
        }
        if (state.step === 5) buildSummary();
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
            if ((dObj - today) / (1000*60*60*24) < 3) { Toast.error('Booking date must be at least 3 days before the event.'); return false; }
            if (!state.available) { Toast.error('This date is already taken. Please choose another.'); return false; }
            state.date = d;
            
            const timeVal = document.getElementById('s1_time').value;
            if (timeVal) {
                const parts = timeVal.split(':');
                const hour = parseInt(parts[0], 10);
                if (hour < 8 || hour >= 22) {
                    Toast.error('Event hours strictly limited between 08:00 AM and 09:59 PM.'); return false;
                }
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
                if (phone.length < 11 || !phone.startsWith('09')) {
                    Toast.error('Please enter a valid 11-digit PH mobile number.');
                    return false;
                }
                if (!name || !phone || !email) { Toast.error('New client name, phone and email are required.'); return false; }
                if (!validateEmail(email)) { Toast.error('Invalid email address.'); return false; }
                // Save new client
                try {
                    const r = await Api.post(BASE + '/src/api/clients.php', {
                        name, phone,
                        email:   document.getElementById('nc_email').value.trim(),
                        address: document.getElementById('nc_address').value.trim(),
                    });
                    state.clientId = r.id;
                    Toast.success('Client added!');
                    // Refresh client list
                    const cd = await Api.get(BASE + '/src/api/clients.php');
                    allClients = cd.clients || [];
                    buildClientSelect();
                } catch(e) { Toast.error(e.message); return false; }
            } else {
                const cid = document.getElementById('s2_client').value;
                if (!cid) { Toast.error('Please select a client.'); return false; }
                state.clientId = cid;
            }
            state.status = 'pending'; // default to pending until DP is confirmed
            state.notes  = document.getElementById('s2_notes').value;
            return true;
        }

        if (step === 3) {
            const num = parseInt(document.getElementById('s3_paxInput').value) || 0;
            if (num < <?= MIN_PAX ?>) { Toast.error('Minimum guest count is <?= MIN_PAX ?>.'); return false; }
            if (num > <?= MAX_PAX ?>) { Toast.error('Maximum guest count is <?= MAX_PAX ?>.'); return false; }
            state.pax = num;

            // Validate staff selection
            const checks = document.querySelectorAll('input.staff-check:checked');
            if (checks.length < requiredStaffCount) {
                Toast.error(`You must select at least ${requiredStaffCount} staff members based on the guest count.`);
                return false;
            }
            
            state.staffIds = Array.from(checks).map(el => {
                const roleSel = el.closest('.staff-row').querySelector('.staff-role-sel');
                return { id: el.value, role: roleSel.value };
            });
            return true;
        }

        if (step === 4) {
            const pax = state.pax;
            if (pax < 50) { Toast.error('Minimum 50 guests required.'); return false; }
            if (!state.packageData) { Toast.error('Could not determine package. Check pax count.'); return false; }
            if (!state.totalCost)   { Toast.error('Price could not be calculated.'); return false; }

            // Dish validation
            if (state.selectedMain.length === 0) {
                Toast.error('Please select at least 1 main dish.'); return false;
            }
            if (state.selectedMain.length > state.maxMain) {
                Toast.error(`Maximum ${state.maxMain} main dishes allowed.`); return false;
            }
            if (state.selectedDesserts.length === 0) {
                Toast.error('Please choose at least 1 dessert.'); return false;
            }

            return true;
        }

        if (step === 5) {
            const dp = parseFloat(document.getElementById('s4_dp').value) || 0;
            const minDP = Math.ceil(state.totalCost * 0.50 * 100) / 100;
            if (dp > 0 && dp < minDP - 0.01) { 
                Toast.error(`Downpayment is below the minimum (₱${minDP.toLocaleString()}).`); 
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
        updateAvailUI('checking');
        clearTimeout(availTimer);
        availTimer = setTimeout(async () => {
            try {
                const d = await Api.get(BASE + '/src/api/availability.php', { date });
                if (d.available) {
                    state.available = true;
                    updateAvailUI('ok');
                } else {
                    state.available = false;
                    updateAvailUI('taken', d.booking);
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

    // Update staff min logic when pax changes
    window.updateStaffMin = function() {
        let p = parseInt(document.getElementById('s3_paxInput').value) || 0;
        if (p > 300) {
            p = 300;
            document.getElementById('s3_paxInput').value = p;
            Toast.warning('Maximum guest count is capped at 300.');
        }

        document.getElementById('s3_paxCount').textContent = p;
        if (p < 100) { requiredStaffCount = 5; state.maxMain = 5; state.maxDessert = 1; }
        else if (p < 150) { requiredStaffCount = 7; state.maxMain = 6; state.maxDessert = 1; }
        else if (p < 200) { requiredStaffCount = 7; state.maxMain = 7; state.maxDessert = 2; }
        else if (p < 250) { requiredStaffCount = 8; state.maxMain = 8; state.maxDessert = 2; }
        else if (p < 300) { requiredStaffCount = 8; state.maxMain = 9; state.maxDessert = 3; }
        else { requiredStaffCount = 8; state.maxMain = 10; state.maxDessert = 3; }
        
        document.getElementById('s3_minStaffCount').textContent = requiredStaffCount;
        
        const ml = document.getElementById('maxMainLabel');
        if(ml) ml.textContent = state.maxMain;
        const md = document.getElementById('maxDessertLabel');
        if(md) md.textContent = state.maxDessert;
        
        // Reset selections when size changes down to avoid breaking limits
        if (state.selectedMain && state.selectedDesserts) {
            state.selectedMain = [];
            state.selectedDesserts = [];
            const mGrid = document.getElementById('mainDishGrid');
            if(mGrid) mGrid.querySelectorAll('.selected').forEach(el=>el.classList.remove('selected'));
            const dGrid = document.getElementById('dessertDishGrid');
            if(dGrid) dGrid.querySelectorAll('.dessert-selected').forEach(el=>el.classList.remove('dessert-selected'));
            const mdl = document.getElementById('mainDishCounter');
            if (mdl) mdl.textContent = `0 / ${state.maxMain}`;
            const ddl = document.getElementById('dessertCounter');
            if (ddl) ddl.textContent = `0 / ${state.maxDessert}`;
        }

        document.getElementById('s3_minStaffCount').textContent = requiredStaffCount;
        updateStaffCounter();
    };

    window.updateStaffCounter = function() {
        const cnt = document.querySelectorAll('input.staff-check:checked').length;
        const el = document.getElementById('s3_counter');
        el.textContent = `${cnt} / ${Math.max(requiredStaffCount, cnt)}`;
        
        if (cnt >= requiredStaffCount) {
            el.style.background = 'rgba(48,209,88,0.1)';
            el.style.color = '#1A7A32';
        } else {
            el.style.background = 'rgba(255,59,48,0.1)';
            el.style.color = '#FF3B30';
        }
    };

    async function fetchAvailableStaff() {
        if (!state.date) return;
        const box = document.getElementById('staffSelectBox');
        try {
            updateStaffMin(); // Refresh required count logic based on pax input constraint
            const d = await Api.get(BASE + '/src/api/staff.php', { available_on: state.date });
            availableStaffList = d.staff || [];
            
            if (!availableStaffList.length) {
                box.innerHTML = `<div class="empty-state"><i class="fas fa-users-slash"></i><p>No active staff found.</p></div>`;
                return;
            }

            box.innerHTML = availableStaffList.map(s => {
                const disable = s.availability !== 'available' ? 'disabled' : '';
                const opac    = disable ? '0.5' : '1';
                const statusHtml = s.availability === 'available' ? '<span style="color:#1A7A32;font-size:10px;">🟢 Available</span>' : 
                                  (s.availability === 'on_leave' ? '<span style="color:#9A5400;font-size:10px;">🟡 On Leave</span>' : '<span style="color:#FF3B30;font-size:10px;">⚫ Booked</span>');
                
                return `
                <div class="staff-row" style="display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid var(--border);opacity:${opac};">
                    <input type="checkbox" class="staff-check form-check-input" value="${s.id}" ${disable} onchange="updateStaffCounter()" style="width:16px;height:16px;">
                    <div style="flex:1;">
                        <div style="font-weight:600;font-size:13px;">${s.name}</div>
                        <div>${statusHtml}</div>
                    </div>
                    <select class="form-control staff-role-sel" style="width:120px;padding:2px 8px;font-size:11px;" ${disable}>
                        <option value="Head Cook">Head Cook</option>
                        <option value="Assistant Cook">Assistant Cook</option>
                        <option value="Waiter" selected>Waiter</option>
                        <option value="Coordinator">Coordinator</option>
                        <option value="Utility">Utility</option>
                    </select>
                </div>
                `;
            }).join('');
            
            // Re-tick already selected ones if we go back
            if (state.staffIds && state.staffIds.length > 0) {
                state.staffIds.forEach(sv => {
                    const cb = box.querySelector(`input.staff-check[value="${sv.id}"]`);
                    if (cb && !cb.disabled) {
                        cb.checked = true;
                        cb.closest('.staff-row').querySelector('select').value = sv.role;
                    }
                });
            }

            updateStaffCounter();

        } catch(e) {
            box.innerHTML = '<div class="text-danger" style="padding:16px;">Failed to load staff list.</div>';
        }
    }

    /* ── PRICING ENGINE (PAX-DRIVEN AUTO-TIER from DB packages) ── */
    window.refreshPackageCalc = function () {
        calcPricing();
    };

    window.calcPricing = function () {
        const pax    = parseInt(document.getElementById('s3_pax').value) || 0;
        const errEl  = document.getElementById('s3_paxError');
        const badge  = document.getElementById('s3_pkgBadge');
        const tierRow = document.getElementById('pr_tierRow');
        const dishPanel = document.getElementById('dishSelectionPanel');

        if (!pax) {
            badge.style.display    = 'none';
            tierRow.style.display  = 'none';
            dishPanel.style.display = 'none';
            document.getElementById('pr_total').textContent     = '₱—';
            document.getElementById('pr_dpNotice').style.display = 'none';
            state.totalCost  = 0;
            state.packageData = null;
            return;
        }
        if (pax < 50) {
            errEl.textContent   = 'Minimum of 50 guests is required.';
            errEl.style.display = 'block';
            badge.style.display = 'none';
            dishPanel.style.display = 'none';
            state.totalCost = 0;
            return;
        }
        errEl.style.display = 'none';

        // ── AUTO-TIER: floor to nearest 50 from packages array ────────────────
        const TIER_STEP = 50;
        let tierPax = Math.floor(pax / TIER_STEP) * TIER_STEP;
        if (tierPax < 50) tierPax = 50;

        // Find matching package from DB data
        let pkg = allPackages.find(p => parseInt(p.pax_count) === tierPax);
        if (!pkg) {
            // pax > max tier: use highest package
            pkg = allPackages.reduce((a, b) =>
                parseInt(a.pax_count) >= parseInt(b.pax_count) ? a : b, allPackages[0]);
            tierPax = parseInt(pkg.pax_count);
        }
        if (!pkg) return;

        const basePax    = parseInt(pkg.pax_count);
        const basePrice  = parseFloat(pkg.price);
        const ratePerPax = basePrice / basePax;
        const extraPax   = Math.max(0, pax - tierPax);
        const extraCost  = Math.round(extraPax * ratePerPax * 100) / 100;
        const total      = Math.round((basePrice + extraCost) * 100) / 100;
        const perPax     = Math.round((total / pax) * 100) / 100;

        state.packageId   = pkg.id;
        state.packageData = pkg;
        state.tierPax     = tierPax;
        state.basePrice    = basePrice;
        state.extraPax    = extraPax;
        state.extraCost   = extraCost;
        state.totalCost   = total;
        
        let p = pax;
        if (p < 100) { state.maxMain = 5; state.maxDessert = 1; }
        else if (p < 150) { state.maxMain = 6; state.maxDessert = 1; }
        else if (p < 200) { state.maxMain = 7; state.maxDessert = 2; }
        else if (p < 250) { state.maxMain = 8; state.maxDessert = 2; }
        else if (p < 300) { state.maxMain = 9; state.maxDessert = 3; }
        else { state.maxMain = 10; state.maxDessert = 3; }

        // ── Badge ───────────────────────────────────────────────
        document.getElementById('s3_pkgLabel').textContent =
            `${pkg.set_name} — ${tierPax} pax · ₱${basePrice.toLocaleString()}`;
        badge.style.display = 'block';

        // ── Price Card ────────────────────────────────────────────
        tierRow.style.display = 'block';
        document.getElementById('pr_tierName').textContent =
            `${pkg.set_name} · ${tierPax} pax (base ₱${basePrice.toLocaleString()})`;
        document.getElementById('pr_baseDesc').textContent =
            `${tierPax} pax × ₱${ratePerPax.toLocaleString('en-PH',{minimumFractionDigits:2})}/pax`;
        document.getElementById('pr_basePrice').textContent =
            `₱${basePrice.toLocaleString('en-PH',{minimumFractionDigits:2})}`;

        if (extraPax > 0) {
            document.getElementById('pr_extraRow').style.display = 'flex';
            document.getElementById('pr_extraDesc').textContent  =
                `${extraPax} extra pax × ₱${ratePerPax.toLocaleString('en-PH',{minimumFractionDigits:2})}/pax`;
            document.getElementById('pr_extraCost').textContent  =
                `+₱${extraCost.toLocaleString('en-PH',{minimumFractionDigits:2})}`;
        } else {
            document.getElementById('pr_extraRow').style.display = 'none';
        }

        document.getElementById('pr_total').textContent  = '₱' + total.toLocaleString('en-PH',{minimumFractionDigits:2});
        document.getElementById('pr_perPax').textContent = '₱' + perPax.toLocaleString('en-PH',{minimumFractionDigits:2}) + '/pax';

        const minDP = Math.ceil(total * 0.50);
        document.getElementById('pr_dpNotice').style.display = 'flex';
        document.getElementById('pr_minDP').textContent =
            '₱' + minDP.toLocaleString('en-PH',{minimumFractionDigits:2});

        // Update counters
        document.getElementById('maxMainLabel').textContent = state.maxMain;
        document.getElementById('maxDessertLabel').textContent = state.maxDessert;
        document.getElementById('mainDishCounter').textContent =
            `${state.selectedMain.length} / ${state.maxMain}`;
        document.getElementById('dessertCounter').textContent =
            `${state.selectedDesserts.length} / ${state.maxDessert}`;

        // Show dish selection panel
        dishPanel.style.display = 'block';

        // Prefill downpayment
        const dpEl = document.getElementById('s4_dp');
        if (!dpEl.value) dpEl.value = minDP.toFixed(2);
        updateSummaryBalances();
    };

    /* ── DISH SELECTION ───────────────────────────────────────────── */
    function buildDishSelection() {
        // Main dishes — checkboxes
        const mainGrid = document.getElementById('mainDishGrid');
        mainGrid.innerHTML = allDishes.mainDishes.map(d => `
            <div class="dish-card" id="mdish_${d.id}" onclick="toggleMain(${d.id})">
                <span style="font-size:13px;">🍲</span> ${d.name}
            </div>
        `).join('');

        // Desserts — multiple-select
        const dessertGrid = document.getElementById('dessertDishGrid');
        dessertGrid.innerHTML = allDishes.desserts.map(d => `
            <div class="dish-card" id="dessert_${d.id}" onclick="toggleDessert(${d.id})">
                <span style="font-size:13px;">🍮</span> ${d.name}
            </div>
        `).join('');
    }

    window.toggleMain = function (id) {
        const el  = document.getElementById('mdish_' + id);
        const idx = state.selectedMain.indexOf(id);
        if (idx > -1) {
            // Deselect
            state.selectedMain.splice(idx, 1);
            el.classList.remove('selected');
        } else {
            // Select (enforce max)
            if (state.selectedMain.length >= state.maxMain) {
                Toast.error(`Maximum ${state.maxMain} main dishes for this package.`);
                return;
            }
            state.selectedMain.push(id);
            el.classList.add('selected');
        }
        document.getElementById('mainDishCounter').textContent =
            `${state.selectedMain.length} / ${state.maxMain}`;
    };

    window.toggleDessert = function (id) {
        const el  = document.getElementById('dessert_' + id);
        const idx = state.selectedDesserts.indexOf(id);
        if (idx > -1) {
            state.selectedDesserts.splice(idx, 1);
            el.classList.remove('dessert-selected');
        } else {
            if (state.selectedDesserts.length >= state.maxDessert) {
                Toast.error(`Maximum ${state.maxDessert} dessert(s) for this guest count.`);
                return;
            }
            state.selectedDesserts.push(id);
            el.classList.add('dessert-selected');
        }
        document.getElementById('dessertCounter').textContent = `${state.selectedDesserts.length} / ${state.maxDessert}`;
    };

    /* ── STEP 4 DOWNPAYMENT ── */
    window.onDPInput = function () {
        updateSummaryBalances();
    };

    function updateSummaryBalances() {
        const total  = state.totalCost;
        const dp     = parseFloat(document.getElementById('s4_dp').value) || 0;
        const minDP  = Math.ceil(total * 0.50 * 100) / 100;
        const balance = Math.max(0, total - dp);
        const errEl  = document.getElementById('s4_dpError');
        const subBtn = document.getElementById('stepSubmitBtn');

        document.getElementById('s4_total').textContent   = Format.peso(total);
        document.getElementById('s4_minDP').textContent   = Format.peso(minDP);
        document.getElementById('s4_balance').textContent = Format.peso(balance);

        state.dpAmount = dp;

        if (dp > 0 && dp < minDP - 0.01) {
            errEl.textContent   = `Minimum downpayment is 50% of total (${Format.peso(minDP)}).`;
            errEl.style.display = 'block';
            subBtn.disabled     = true;
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
        state.termsOk     = document.getElementById('s4_terms').checked;
        const dp          = parseFloat(document.getElementById('s4_dp').value) || 0;
        const minDP       = Math.ceil(state.totalCost * 0.50 * 100) / 100;
        const dpOk        = dp === 0 || (dp >= minDP - 0.01 && dp <= state.totalCost + 0.01);
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
        const mainNames = state.selectedMain.map(id => {
            const d = allDishes.mainDishes.find(x => x.id == id);
            return d ? d.name : '';
        }).filter(Boolean);

        const dessertNames = state.selectedDesserts.map(id => {
            const d = allDishes.desserts.find(x => x.id == id);
            return d ? d.name : '';
        }).filter(Boolean);

        document.getElementById('summaryCard').innerHTML = `
            <div style="display:grid; gap:7px; font-size:12.5px;">
                <div style="display:flex; justify-content:space-between;"><span style="color:rgba(60,60,67,0.5);">Event Date</span><strong>${Format.dateShort(state.date)}</strong></div>
                ${state.time ? `<div style="display:flex; justify-content:space-between;"><span style="color:rgba(60,60,67,0.5);">Time</span><strong>${Format.time(state.time)}</strong></div>` : ''}
                ${state.location ? `<div style="display:flex; justify-content:space-between;"><span style="color:rgba(60,60,67,0.5);">Venue</span><strong style="text-align:right; max-width:160px;">${state.location}</strong></div>` : ''}
                <div style="display:flex; justify-content:space-between;"><span style="color:rgba(60,60,67,0.5);">Client</span><strong>${clientName}</strong></div>
                <div style="height:0.5px; background:rgba(60,60,67,0.1); margin:2px 0;"></div>
                <div style="display:flex; justify-content:space-between;"><span style="color:rgba(60,60,67,0.5);">Package</span><strong>${pkgLabel}</strong></div>
                <div style="display:flex; justify-content:space-between;"><span style="color:rgba(60,60,67,0.5);">Guests</span><strong>${state.pax} pax</strong></div>
                <div style="display:flex; justify-content:space-between;"><span style="color:rgba(60,60,67,0.5);">Base Price</span><strong>${Format.peso(state.basePrice)}</strong></div>
                ${state.extraPax > 0 ? `<div style="display:flex; justify-content:space-between;"><span style="color:rgba(60,60,67,0.5);">Extra ${state.extraPax} pax</span><strong style="color:#FF9500;">+${Format.peso(state.extraCost)}</strong></div>` : ''}
                <div style="height:0.5px; background:rgba(60,60,67,0.1); margin:2px 0;"></div>
                <div style="display:flex; justify-content:space-between;"><span style="font-weight:700;">Total</span><strong style="font-size:16px; color:var(--sys-green);">${Format.peso(state.totalCost)}</strong></div>
                <div style="height:0.5px; background:rgba(60,60,67,0.1); margin:4px 0;"></div>
                ${mainNames.length > 0 ? `
                <div>
                    <div style="color:rgba(60,60,67,0.5); margin-bottom:4px;">Main Dishes (${mainNames.length})</div>
                    <div style="display:flex; flex-wrap:wrap; gap:4px;">
                        ${mainNames.map(n => `<span style="background:rgba(48,209,88,0.1); color:#1A7A32; border-radius:6px; padding:2px 8px; font-size:11px; font-weight:600;">${n}</span>`).join('')}
                    </div>
                </div>` : ''}
                ${dessertNames.length > 0 ? `
                <div style="display:flex; justify-content:space-between;"><span style="color:rgba(60,60,67,0.5);">Desserts (${dessertNames.length})</span><strong style="text-align:right;">${dessertNames.join(', ')}</strong></div>` : ''}
                <div style="display:flex; justify-content:space-between;"><span style="color:rgba(60,60,67,0.5);">Rice</span><strong>🍚 Included</strong></div>
            </div>
        `;

        const dpEl = document.getElementById('s4_dp');
        if (!dpEl.value) dpEl.value = Math.ceil(state.totalCost * 0.50).toFixed(2);
        updateSummaryBalances();
        document.getElementById('stepSubmitBtn').disabled = true;
    }

    /* ── SUBMIT ─────────────────────────────────────────────── */
    window.submitBooking = async function () {
        const btn = document.getElementById('stepSubmitBtn');
        if (!state.termsOk) { Toast.error('Please agree to the Terms & Conditions.'); return; }

        const dp    = parseFloat(document.getElementById('s4_dp').value) || 0;
        const minDP = Math.ceil(state.totalCost * 0.50 * 100) / 100;
        if (dp > 0 && dp < minDP - 0.01) { Toast.error('Downpayment is below the 50% minimum.'); return; }

        const allSelectedDishes = [
            ...state.selectedMain,
            ...state.selectedDesserts,
        ];

        Form.setLoading(btn, true);
        try {
            const payload = {
                client_id:          state.clientId,
                event_date:         state.date,
                event_time:         state.time        || null,
                event_location:     state.location    || null,
                pax_count:          state.pax,
                booking_status:     'confirmed',
                notes:              state.notes   || null,
                selected_dishes:    allSelectedDishes,
                downpayment:        dp > 0 ? dp : null,
                downpayment_method: document.getElementById('s4_dpMethod').value,
                downpayment_ref:    document.getElementById('s4_dpRef').value || null,
            };

            const res = await Api.post(BASE + '/src/api/bookings.php', payload);
            const isConfirmed = res.booking_status === 'confirmed';

            // Now assign the staff
            if (state.staffIds && state.staffIds.length > 0 && res.id) {
                try {
                    await Api.post(BASE + 'src/api/bookings.php', {
                        link_staff: true,
                        booking_id: res.id,
                        staff_roles: state.staffIds
                    });
                } catch(e) { console.error("Staff assignment non-fatal error", e); }
            }

            if (isConfirmed) {
                Toast.success(
                    `${res.package_name} booking confirmed! Downpayment of ${Format.peso(dp)} recorded.`,
                    6000
                );
            } else {
                Toast.warning(
                    `${res.package_name} booking saved as Pending. ` +
                    `Record a downpayment of at least ${Format.peso(res.total_cost * 0.5)} in Financials to confirm it.`,
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
