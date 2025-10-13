<?php
// /customer/quote_request.php — Quote/Schedule form with email + phone
declare(strict_types=1);
session_start();
ini_set('display_errors','1'); error_reporting(E_ALL);

if (empty($_SESSION['user_id'])) { header('Location:/login.php'); exit; }
$userId = (int)$_SESSION['user_id'];
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));

// Define the absolute path to the uploads directory relative to the current script's DOCUMENT_ROOT
define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . '/uploads/');

require __DIR__.'/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
if (!isset($conn) && isset($mysqli) && $mysqli instanceof mysqli) $conn=$mysqli;
$conn->set_charset('utf8mb4');

function h($x){ return htmlspecialchars((string)$x,ENT_QUOTES,'UTF-8'); }

/* Prefill email/phone from users record if present */
$prefill_email=''; $prefill_phone='';
try {
  $st = $conn->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
  $st->bind_param('i',$userId); $st->execute();
  if ($u=$st->get_result()->fetch_assoc()){
    $prefill_email = (string)($u['email'] ?? $u['user_email'] ?? '');
    $prefill_phone = (string)($u['phone'] ?? $u['telephone'] ?? '');
  }
  $st->close();
} catch (Throwable $e){ /* ignore */ }

/* --- JOB TYPE HANDLER --- */
$view = $_GET['view'] ?? $_POST['view'] ?? 'select'; // 'select', 'quote', or 'outbound'
$request_type = ''; // Will hold 'quote' or 'outbound' for meta storage

/* POST handler */
$ok=false; $orderId=0; $err='';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Bad CSRF'); }
  
  // Determine if it's a QUOTE or OUTBOUND request based on the submitted form
  $request_type = trim((string)($_POST['request_type'] ?? ''));
  $view = $request_type; // Set view back to the form type in case of error

  // --- Common Fields ---
  $email = trim((string)($_POST['email'] ?? ''));
  $phone = trim((string)($_POST['phone'] ?? ''));
  $notes = trim((string)($_POST['notes'] ?? ''));
  
  // Initialize file path variable
  $uploaded_label_filename = ''; 

  // --- Validation ---
  if (!$err && $email === '') { $err = 'Email is required so we can reach you.'; }
  if (!$err && $phone === '') { $err = 'Phone number is required.'; }
  if (!$err && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $err = 'Please enter a valid email address.'; }

  if ($request_type === 'quote') {
    // --- QUOTE (Process 1) Specific Validation ---
    $pickup = trim((string)($_POST['pickup'] ?? ''));
    $dropoff = trim((string)($_POST['dropoff'] ?? ''));
    $pkg = trim((string)($_POST['package_size'] ?? ''));
    $when = trim((string)($_POST['requested_datetime'] ?? ''));

    if (!$err && ($pickup === '' || $dropoff === '' || $pkg === '')) {
      $err = 'Pickup, destination, and package size are required for a Quote.';
    }

  } elseif ($request_type === 'outbound') {
    // --- OUTBOUND (Process 3) Specific Validation ---
    $out_type = trim((string)($_POST['shipment_type'] ?? '')); // return or new_shipment
    $out_size = trim((string)($_POST['out_size'] ?? ''));
    $out_weight = (int)($_POST['out_weight'] ?? 0);
    $out_date = trim((string)($_POST['pickup_date'] ?? ''));
    $out_window = trim((string)($_POST['pickup_window'] ?? ''));
    
    // New/Updated Shipment Fields
    $out_pickup_address = trim((string)($_POST['out_pickup_address'] ?? '')); // NEW FIELD
    $dest_zip = trim((string)($_POST['dest_zip'] ?? ''));
    $out_len = (float)($_POST['out_len'] ?? 0);
    $out_wid = (float)($_POST['out_wid'] ?? 0);
    $out_hgt = (float)($_POST['out_hgt'] ?? 0);
    $calculated_rate = (float)($_POST['calculated_rate'] ?? 0.0); // Rate is now calculated via JS

    if (!$err && $out_pickup_address === '') {
        $err = 'Pickup address is required to schedule the shipment.';
    }
    
    if (!$err && ($out_type === '' || $out_size === '' || $out_weight <= 0 || $out_date === '' || $out_window === '')) {
      $err = 'Shipment Type, Size, Weight, Date, and Time Window are required.';
    }
    
    if (!$err && $out_type === 'new_shipment') {
        if ($dest_zip === '' || $out_len <= 0 || $out_wid <= 0 || $out_hgt <= 0 || $calculated_rate <= 0) {
            $err = 'Destination ZIP, dimensions, and a valid calculated rate are required for New Shipments.';
        }
    }
    
    // --- FILE UPLOAD LOGIC for Prepaid Return ---
    if (!$err && $out_type === 'return') {
        if (!empty($_FILES['label_upload']) && $_FILES['label_upload']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['label_upload'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_types = ['pdf', 'png', 'jpg', 'jpeg'];

            if (!in_array($file_ext, $allowed_types)) {
                $err = 'Only PDF, JPG, and PNG files are allowed for labels.';
            }

            if (!$err) {
                // Create a unique filename: [user ID]-[timestamp]-[random]-[ext]
                $uploaded_label_filename = sprintf(
                    '%d-%d-%s.%s',
                    $userId,
                    time(),
                    bin2hex(random_bytes(4)),
                    $file_ext
                );
                $destination = UPLOAD_DIR . $uploaded_label_filename;

                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    $err = 'File upload failed due to a server error.';
                    $uploaded_label_filename = ''; // Clear filename on failure
                }
            }
        } else {
             // File upload is REQUIRED for prepaid return
             if ($_FILES['label_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
                $err = 'Label file upload failed. Please try again.';
             } else {
                 $err = 'The prepaid shipping label is required for return shipments.';
             }
        }
    }
    
    // Assign QUOTE/ORDER fields for DB insertion from OUTBOUND data
    $pickup = $out_pickup_address; // UPDATED to use new input field
    $dropoff = ($out_type === 'return') ? 'Facility (Return Label Ready)' : 'Customer Destination ZIP: ' . $dest_zip; 
    $pkg = $out_size . ' / ' . $out_weight . ' lbs';
    $when = $out_date . ' ' . $out_window;

  } else {
    // Unknown or missing request type
    $err = 'Invalid request type selected.';
  }


  if (!$err) {
    // --- SHARED INSERT LOGIC ---
    $cols = []; $res=$conn->query("SHOW COLUMNS FROM orders"); while($c=$res->fetch_assoc()) $cols[$c['Field']]=1; $res->close();

    // Build INSERT that touches only columns that exist
    $fields = ['user_id']; $place = ['?']; $types='i'; $args=[ $userId ];
    if (!empty($cols['pickup_address'])) { $fields[]='pickup_address'; $place[]='?'; $types.='s'; $args[]=$pickup; }
    if (!empty($cols['delivery_address'])) { $fields[]='delivery_address'; $place[]='?'; $types.='s'; $args[]=$dropoff; }
    if (!empty($cols['package_size'])) { $fields[]='package_size'; $place[]='?'; $types.='s'; $args[]=$pkg; }
    
    // Status is always Pickup Scheduled for a confirmed Outbound or Quote Pending for custom quote
    $initial_status = ($request_type === 'quote') ? 'Quote Pending' : 'Pickup Scheduled';
    if (!empty($cols['status'])) { $fields[]='status'; $place[]='?'; $types.='s'; $args[]=$initial_status; }

    $sql="INSERT INTO orders (".implode(',',$fields).") VALUES (".implode(',',$place).")";
    $st=$conn->prepare($sql);
    $st->bind_param($types, ...$args);
    $st->execute();
    $orderId = $st->insert_id;
    $st->close();

    // Write meta 
    $meta = [
      'contact_email'     => $email,
      'contact_phone'     => $phone,
      'request_type'      => $request_type, 
      'source'            => 'customer_portal'
    ];
    
    if ($request_type === 'quote') {
        $meta['pickup_address'] = $pickup;
        $meta['delivery_address'] = $dropoff;
        $meta['package_size'] = $pkg;
        $meta['requested_datetime'] = $when;
        $meta['notes'] = $notes;
    } elseif ($request_type === 'outbound') {
        $meta['pickup_address'] = $out_pickup_address; // Meta for Outbound Pickup
        $meta['shipment_type'] = $out_type;
        $meta['out_size'] = $out_size;
        $meta['out_weight'] = (string)$out_weight;
        $meta['pickup_date'] = $out_date;
        $meta['pickup_window'] = $out_window;
        $meta['collection_instructions'] = trim((string)($_POST['out_instructions'] ?? ''));
        
        if ($out_type === 'return' && $uploaded_label_filename) {
            $meta['uploaded_label_file'] = $uploaded_label_filename;
        } elseif ($out_type === 'new_shipment') {
            $meta['dest_zip'] = $dest_zip;
            $meta['out_dimensions'] = "{$out_len}x{$out_wid}x{$out_hgt} in";
            $meta['final_rate'] = number_format($calculated_rate, 2);
            $meta['payment_status'] = 'Paid (Simulated)'; // Simulate successful payment
        }
    }

    $mst=$conn->prepare("INSERT INTO order_meta (order_id, meta_key, meta_value) VALUES (?,?,?)");
    foreach ($meta as $k=>$v) {
      if ($v === '' || $v === null) continue;
      $kv=(string)$k; $vv=(string)$v;
      $mst->bind_param('iss', $orderId, $kv, $vv);
      $mst->execute();
    }
    $mst->close();

    // Optional emails (Copied from existing logic)
    try {
      $emails = $_SERVER['DOCUMENT_ROOT'].'/emails.php';
      if (is_file($emails)) {
        require_once $emails;
        if (function_exists('send_quote_request_admin'))    { @send_quote_request_admin($orderId, $conn); }
        if (function_exists('send_quote_request_customer')) { @send_quote_request_customer($orderId, $conn); }
      }
    } catch (Throwable $e){ error_log('quote mail: '.$e->getMessage()); }

    $ok=true;
  }
}
?>
<!doctype html>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Get a Quote / Schedule a Pickup</title>
<style>
body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;background:#f6f7fb;margin:0}
.wrap{max-width:780px;margin:26px auto;padding:0 14px}
.card{background:#fff;border:1px solid #e9edf3;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.05);padding:16px;margin-bottom:16px}
h2{margin:0 0 12px}
label{display:block;margin:10px 0 6px;color:#374151;font-weight:600}
input,textarea,select{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;font-size:16px}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.btn{background:#0d6efd;color:#fff;border:0;padding:10px 14px;border-radius:8px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block;text-align:center;}
.btn.gray{background:#6b7280}
.note{margin:10px 0;padding:10px;border-radius:8px}
.ok{background:#e7f5ff;border:1px solid #a5d8ff}
.err{background:#fde2e1;border:1px solid #f5a097}

/* New Selector Styles */
.selector-option { 
    display: block; width: 100%; text-align: left; padding: 15px;
    border: 2px solid #ccc; border-radius: 8px; margin-bottom: 12px;
    transition: all 0.2s ease; cursor: pointer;
}
.selector-option:hover { 
    box-shadow: 0 4px 8px rgba(0,0,0,0.05); 
    border-color: #0d6efd;
}
.selector-option h3 { margin: 0 0 4px; font-size: 1.25em; } 
.selector-option p { margin: 0; font-size: 1em; color: #374151; font-weight: 500;} 
.btn-select-quote { background:#0d6efd; color: #fff;}
.btn-select-outbound { background:#28a745; color: #fff;}
.btn-select-outbound:hover { background:#1e7e34; }

/* Hidden field styles for dynamic content */
#label_upload_group, #new_shipment_details {
    display: none;
    margin-top: 10px;
}
#label_upload_group label, #new_shipment_details label {
    font-weight: 600;
}

/* Rate Display */
#rate_output {
    margin-top: 15px;
    padding: 10px 15px;
    border-radius: 8px;
    background: #e6ffed;
    border: 1px solid #1e7e34;
    text-align: center;
    font-size: 1.2em;
    font-weight: 700;
}
#rate_output button {
    margin-top: 10px;
}

/* Responsive adjustments for mobile */
@media (max-width: 600px) {
    .row {
        grid-template-columns: 1fr;
    }
    .wrap {
        margin: 14px auto; 
        padding: 0 8px;
    }
    .selector-option {
        padding: 12px;
    }
}
</style>

<div class="wrap">
  <div class="card">
    <h2>Start a Quote / Schedule a Pickup</h2>

    <?php if ($ok): ?>
      <div class="note ok">
        Thanks! Your request was received. Reference #<?= (int)$orderId ?>. 
        <?php if ($request_type === 'quote'): ?>
            An admin will review it and send a price shortly.
        <?php else: /* outbound */ ?>
            Your pickup is scheduled and <?= $out_type === 'new_shipment' ? 'payment is confirmed' : 'label is received' ?>! Check your dashboard for details.
        <?php endif; ?>
      </div>
      <p><a class="btn" href="/customer/dashboard.php">Back to dashboard</a></p>

    <?php else: // NOT OK / Form Display ?>

      <?php if ($err): ?><div class="note err"><?= h($err) ?></div><?php endif; ?>

      <?php if ($view === 'select'): ?>
        <!-- STEP 1: JOB TYPE SELECTION -->
        <p>Please select the type of service you need to start.</p>
        <div style="margin-top:20px;">
            <button class="selector-option btn-select-quote" onclick="window.location.href='?view=quote'">
                <h3>Custom Pickup & Delivery (Quote Request)</h3>
                <p style="color:#fff;">For non-standard, door-to-door deliveries (requires admin pricing).</p>
            </button>
            <button class="selector-option btn-select-outbound" onclick="window.location.href='?view=outbound'">
                <h3>Outbound Return / Shipment</h3>
                <p style="color:#fff;">For scheduling a pickup to send a package out (returns or new labels).</p>
            </button>
        </div>
      
      <?php elseif ($view === 'quote'): ?>
        <!-- PROCESS 1: CUSTOM QUOTE FIELDS -->
        <h3 style="margin-top: 0;">Custom Pickup Details</h3>
        <p style="margin-top: -8px; font-size: 0.9em; color: #6b7280;">
          Start typing in the address fields to use Google Autocomplete.
        </p>
        <form method="post" novalidate>
          <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
          <input type="hidden" name="request_type" value="quote">

          <label>Pickup Address</label>
          <input type="text" name="pickup" id="pickup_address_quote" required 
                 placeholder="Street Address, City, State, ZIP"
                 value="<?= h($_POST['pickup'] ?? '') ?>">

          <label>Destination Address</label>
          <input type="text" name="dropoff" id="dropoff_address_quote" required 
                 placeholder="Street Address, City, State, ZIP"
                 value="<?= h($_POST['dropoff'] ?? '') ?>">

          <div class="row">
            <div>
              <label>Package Size</label>
              <select name="package_size" required>
                <?php
                  $sizes = ['Small','Medium','Large','XL'];
                  $cur = (string)($_POST['package_size'] ?? '');
                  foreach ($sizes as $s) {
                    $sel = ($cur===$s)?' selected':'';
                    echo "<option$sel>".h($s)."</option>";
                  }
                ?>
              </select>
            </div>
            <div>
              <label>Requested Date/Time (optional)</label>
              <input type="text" name="requested_datetime" placeholder="e.g., Tue 3–5pm"
                     value="<?= h($_POST['requested_datetime'] ?? '') ?>">
            </div>
          </div>

          <div class="row">
            <div>
              <label>Email</label>
              <input type="email" name="email" required
                     value="<?= h($_POST['email'] ?? $prefill_email) ?>" placeholder="you@example.com">
            </div>
            <div>
              <label>Phone</label>
              <input type="tel" name="phone" required
                     value="<?= h($_POST['phone'] ?? $prefill_phone) ?>" placeholder="(555) 123-4567">
            </div>
          </div>

          <label>Notes / Instructions (optional)</label>
          <textarea name="notes" rows="3" placeholder="Anything we should know? Gate code, elevator, etc."><?= h($_POST['notes'] ?? '') ?></textarea>

          <div style="margin-top:12px;display:flex;gap:8px">
            <button class="btn btn-select-quote" type="submit">Submit Quote Request</button>
            <a class="btn gray" href="?view=select" role="button">Back to Options</a>
          </div>
        </form>

      <?php elseif ($view === 'outbound'): ?>
        <!-- PROCESS 3: OUTBOUND SHIPMENT FIELDS (New Differentiator) -->
        <h3 style="margin-top: 0;">Outbound Shipment Details</h3>
        <p style="margin-top: -8px; font-size: 0.9em; color: #6b7280;">
          Start typing in the address fields to use Google Autocomplete.
        </p>
        <form method="post" id="outboundForm" novalidate enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
          <input type="hidden" name="request_type" value="outbound">
          <input type="hidden" name="calculated_rate" id="calculated_rate_input" value=""> <!-- Holds the calculated rate -->


          <!-- 1. Shipment Type & Package Info -->
          <fieldset style="border:1px solid #d1d5db;padding:12px;border-radius:8px;">
            <legend style="font-weight:700;padding:0 8px;margin-left:-8px;">1. Shipment Type & Package Info</legend>
            
            <!-- Radio Button Group for Shipment Type -->
            <div onchange="updateOutboundVisibility()">
                <label style="font-weight:normal;">
                  <input type="radio" name="shipment_type" value="return" required 
                         <?php if (($_POST['shipment_type'] ?? '') === 'return') echo 'checked'; ?>
                         style="width:auto;display:inline-block;margin-right:5px;">
                  Prepaid Return (Label Ready)
                </label>
                <label style="font-weight:normal;">
                  <input type="radio" name="shipment_type" value="new_shipment" 
                         <?php if (($_POST['shipment_type'] ?? '') === 'new_shipment' || !($_POST['shipment_type'] ?? '')) echo 'checked'; ?>
                         style="width:auto;display:inline-block;margin-right:5px;">
                  New Shipment (Need Label)
                </label>
            </div>
            
            <!-- Conditional Fields: Prepaid Label Upload -->
            <div id="label_upload_group">
                <label for="label_upload" style="font-weight:600;">Upload Prepaid Label (PDF, JPG, PNG)</label>
                <input type="file" name="label_upload" id="label_upload" accept=".pdf, .jpg, .jpeg, .png" 
                       style="padding: 6px 10px; border: 1px solid #0d6efd;">
                <p style="margin-top:5px;font-size:0.9em;color:#6b7280;font-weight:normal;">
                    We require a prepaid label to schedule the pickup for a return.
                </p>
            </div>
            
            <!-- Conditional Fields: New Shipment Details for Rate Calculation -->
            <div id="new_shipment_details">
                <label for="dest_zip">Destination ZIP Code</label>
                <input type="text" name="dest_zip" id="dest_zip" required pattern="[0-9]{5}" placeholder="e.g., 90210"
                       value="<?= h($_POST['dest_zip'] ?? '') ?>" oninput="calculateRate()">
                <p style="margin-top:5px;font-size:0.9em;color:#6b7280;font-weight:normal;">
                    Dimensions (in inches, for rate calculation):
                </p>
                <div class="row">
                    <div>
                        <label style="margin-top:0;">Length (in)</label>
                        <input type="number" name="out_len" id="out_len" required min="1" step="0.1" 
                               value="<?= h($_POST['out_len'] ?? '10') ?>" oninput="calculateRate()">
                    </div>
                    <div>
                        <label style="margin-top:0;">Width (in)</label>
                        <input type="number" name="out_wid" id="out_wid" required min="1" step="0.1" 
                               value="<?= h($_POST['out_wid'] ?? '10') ?>" oninput="calculateRate()">
                    </div>
                    <div>
                        <label style="margin-top:0;">Height (in)</label>
                        <input type="number" name="out_hgt" id="out_hgt" required min="1" step="0.1" 
                               value="<?= h($_POST['out_hgt'] ?? '5') ?>" oninput="calculateRate()">
                    </div>
                </div>
            </div>
            <!-- End Conditional Fields -->

            <div class="row">
              <div>
                <label>Estimated Size (Visual)</label>
                <select name="out_size" id="out_size" required>
                    <?php
                      $sizes = ['Small','Medium','Large','XL'];
                      $cur = (string)($_POST['out_size'] ?? '');
                      foreach ($sizes as $s) {
                        $sel = ($cur===$s)?' selected':'';
                        echo "<option$sel>".h($s)."</option>";
                      }
                    ?>
                </select>
              </div>
              <div>
                <label>Estimated Weight (lbs)</label>
                <input type="number" name="out_weight" id="out_weight" required placeholder="e.g., 5" min="1"
                       value="<?= h($_POST['out_weight'] ?? '5') ?>" oninput="calculateRate()">
              </div>
            </div>
          </fieldset>
          
          <!-- 2. Schedule Pickup -->
          <fieldset style="border:1px solid #d1d5db;padding:12px;border-radius:8px;margin-top:12px;">
            <legend style="font-weight:700;padding:0 8px;margin-left:-8px;">2. Schedule Pickup</legend>
            
            <label>Pickup Address (Required)</label>
            <input type="text" name="out_pickup_address" id="out_pickup_address" required 
                   placeholder="Street Address, City, State, ZIP"
                   value="<?= h($_POST['out_pickup_address'] ?? '') ?>">

            <div class="row">
              <div>
                <label>Pickup Date</label>
                <input type="date" name="pickup_date" required
                       value="<?= h($_POST['pickup_date'] ?? date('Y-m-d')) ?>">
              </div>
              <div>
                <label>Time Window</label>
                <select name="pickup_window" required>
                    <?php
                      $windows = ['9:00 AM - 12:00 PM','12:00 PM - 3:00 PM','3:00 PM - 6:00 PM'];
                      $cur = (string)($_POST['pickup_window'] ?? '');
                      foreach ($windows as $w) {
                        $sel = ($cur===$w)?' selected':'';
                        echo "<option$sel>".h($w)."</option>";
                      }
                    ?>
                </select>
              </div>
            </div>

            <label>Collection Instructions</label>
            <textarea name="out_instructions" rows="3" placeholder="Where is the package located? Gate code, etc."><?= h($_POST['out_instructions'] ?? '') ?></textarea>
          </fieldset>
          
          <!-- Shared Contact Fields -->
          <div style="margin-top:12px;">
            <label style="font-weight:700;">3. Contact Information</label>
            <div class="row">
              <div>
                <label style="font-weight:normal;">Email</label>
                <input type="email" name="email" required
                       value="<?= h($_POST['email'] ?? $prefill_email) ?>" placeholder="you@example.com">
              </div>
              <div>
                <label style="font-weight:normal;">Phone</label>
                <input type="tel" name="phone" required
                       value="<?= h($_POST['phone'] ?? $prefill_phone) ?>" placeholder="(555) 123-4567">
              </div>
            </div>
          </div>
          
          <!-- Dynamic Rate Display and Submission Button -->
          <div id="rate_output">
             <!-- Content updated by JavaScript -->
             <p>Enter details above to see rate.</p>
          </div>

          <div style="margin-top:12px;display:flex;gap:8px">
            <!-- This button is hidden and replaced by a button inside #rate_output for New Shipments -->
            <button class="btn btn-select-outbound" type="submit" id="schedule_return_btn">Schedule Return Pickup</button>
            <a class="btn gray" href="?view=select" role="button">Back to Options</a>
          </div>
        </form>

        <script>
            // Add a global function to initialize Google Autocomplete
            function initAutocomplete() {
                // Autocomplete for Custom Quote fields
                const quotePickup = document.getElementById('pickup_address_quote');
                if (quotePickup) new google.maps.places.Autocomplete(quotePickup, { types: ['address'] });

                const quoteDropoff = document.getElementById('dropoff_address_quote');
                if (quoteDropoff) new google.maps.places.Autocomplete(quoteDropoff, { types: ['address'] });

                // Autocomplete for Outbound Shipment field
                const outboundPickup = document.getElementById('out_pickup_address');
                if (outboundPickup) new google.maps.places.Autocomplete(outboundPickup, { types: ['address'] });
            }


            /**
             * SIMPLE STATIC RATE CALCULATION LOGIC (Client-Side)
             * This function simulates a carrier rate lookup based on weight and dimensions.
             */
            function calculateRate() {
                const weight = parseFloat(document.getElementById('out_weight').value) || 0;
                const len = parseFloat(document.getElementById('out_len').value) || 0;
                const wid = parseFloat(document.getElementById('out_wid').value) || 0;
                const hgt = parseFloat(document.getElementById('out_hgt').value) || 0;
                const zip = document.getElementById('dest_zip').value;
                const type = document.querySelector('input[name="shipment_type"]:checked')?.value;
                const rateOutput = document.getElementById('rate_output');
                const rateInput = document.getElementById('calculated_rate_input');
                let rate = 0;
                let valid = true;
                
                // Hide the default return button
                document.getElementById('schedule_return_btn').style.display = 'none';

                if (type === 'return') {
                    // Prepaid Returns don't need rate calculation, reset output
                    rateOutput.innerHTML = '<p>Click **Schedule Return Pickup** below to continue.</p>';
                    // Show the return button
                    document.getElementById('schedule_return_btn').style.display = 'block';
                    rateInput.value = '';
                    return;
                }

                // --- Validation for New Shipment ---
                if (weight <= 0 || len <= 0 || wid <= 0 || hgt <= 0 || zip.length < 5) {
                    rateOutput.innerHTML = '<p>Enter required package details and destination ZIP to calculate rate.</p>';
                    rateInput.value = '';
                    return;
                }

                // --- Dimensional Weight Calculation (L*W*H / 139) ---
                const dimWeight = (len * wid * hgt) / 139;
                const billableWeight = Math.ceil(Math.max(weight, dimWeight)); // Billable weight is the greater of actual or dimensional, rounded up

                // --- Static Rate Tiers based on Billable Weight ---
                if (billableWeight <= 5) {
                    rate = 12.50;
                } else if (billableWeight <= 10) {
                    rate = 18.00;
                } else if (billableWeight <= 20) {
                    rate = 26.50;
                } else if (billableWeight <= 50) {
                    rate = 45.00;
                } else {
                    rate = 45.00 + (billableWeight - 50) * 1.50; // Over 50 lbs is $1.50 per additional pound
                }
                
                // --- Simple Distance Surcharge Simulation (Based on ZIP first digit) ---
                const zipStart = zip.substring(0, 1);
                let surcharge = 0;
                if (['0','1','2'].includes(zipStart)) {
                    surcharge = 0; // Local
                } else if (['3','4','5'].includes(zipStart)) {
                    surcharge = 5.00; // Regional
                } else if (['6','7','8'].includes(zipStart)) {
                    surcharge = 10.00; // Farther
                } else {
                    surcharge = 15.00; // National
                }

                rate += surcharge;

                // Final display
                const finalRate = parseFloat(rate.toFixed(2));
                rateInput.value = finalRate;

                rateOutput.innerHTML = `
                    <p style="margin-bottom:8px;">
                        Your estimated shipping rate is: 
                        <span style="font-size:1.5em;color:#1e7e34;margin-left:8px;">$${finalRate.toFixed(2)}</span>
                    </p>
                    <p style="font-size:0.9em;color:#6b7280;font-weight:normal;margin-bottom:10px;">
                        (Based on billable weight of ${billableWeight} lbs and Zone ${zipStart})
                    </p>
                    <button class="btn btn-select-outbound" type="submit" style="width:100%;" 
                            onclick="simulatePayment()">
                        Confirm Rate & Schedule Pickup
                    </button>
                `;
            }

            /**
             * Function to manage visibility of conditional fields (Label vs. New Shipment details)
             */
            function updateOutboundVisibility() {
                const type = document.querySelector('input[name="shipment_type"]:checked')?.value;
                const uploadGroup = document.getElementById('label_upload_group');
                const newShipmentGroup = document.getElementById('new_shipment_details');
                const returnBtn = document.getElementById('schedule_return_btn');
                const rateOutput = document.getElementById('rate_output');
                
                if (type === 'return') {
                    uploadGroup.style.display = 'block';
                    newShipmentGroup.style.display = 'none';
                    returnBtn.style.display = 'block';
                    rateOutput.innerHTML = '<p>Click **Schedule Return Pickup** below to continue.</p>';
                    rateOutput.style.display = 'block';
                } else if (type === 'new_shipment') {
                    uploadGroup.style.display = 'none';
                    newShipmentGroup.style.display = 'block';
                    returnBtn.style.display = 'none';
                    rateOutput.style.display = 'block';
                    calculateRate(); // Recalculate when switching to this view
                } else {
                     // Should only happen if nothing is checked, hide everything
                    uploadGroup.style.display = 'none';
                    newShipmentGroup.style.display = 'none';
                    returnBtn.style.display = 'none';
                    rateOutput.style.display = 'none';
                }
            }

            /**
             * Simulates a payment gateway action before submitting the form.
             */
            function simulatePayment() {
                // In a real application, this would trigger a Stripe/PayPal modal.
                // We submit the form directly here since payment is simulated.
                const form = document.getElementById('outboundForm');
                // The form will submit, and the PHP handler will check calculated_rate_input
                form.submit();
            }

            document.addEventListener('DOMContentLoaded', () => {
                updateOutboundVisibility();
            });
        </script>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<!-- Load Google Maps Places API for Autocomplete -->
<!-- API Key inserted by user request: AIzaSyDpHb2wpGdHLWY5fZIEKDGZRAbEMHb06VM -->
<script async defer src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDpHb2wpGdHLWY5fZIEKDGZRAbEMHb06VM&libraries=places&callback=initAutocomplete"></script>
