<?php
ob_start();
session_start();
require '../../customer/menu/db_connect.php';

// Check database connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Database connection failed"));
    exit();
}

// Check session timeout (30 minutes)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    session_unset();
    session_destroy();
    header("Location: /Online-Fast-Food/login.php?message=" . urlencode("Your session has expired. Please log in again."));
    exit();
}
$_SESSION['last_activity'] = time();

// Check if the user is logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: /Online-Fast-Food/login.php");
    exit();
}

$customerId = (int)$_SESSION['customer_id'];

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Log file for debugging
$logFile = 'delivery_addresses_errors.log';
$logMessage = function($message) use ($logFile) {
    $message = filter_var($message, FILTER_SANITIZE_STRING);
    error_log(date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, 3, $logFile);
};

// Fetch delivery addresses
$deliveryAddresses = [];
$stmt = $conn->prepare("SELECT id, street_address, city, postal_code FROM delivery_addresses WHERE customer_id = ? ORDER BY created_at DESC");
if (!$stmt) {
    $logMessage("Prepare failed for fetch: " . $conn->error);
    header("Location: /Online-Fast-Food/error.php?message=" . urlencode("Database error"));
    exit();
}
$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $deliveryAddresses[] = $row;
}
$stmt->close();

// Handle adding a new delivery address
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_delivery_address'])) {
    header('Content-Type: application/json');
    $logMessage("Add request: " . json_encode($_POST));

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $logMessage("CSRF validation failed. Sent: " . ($_POST['csrf_token'] ?? 'none') . ", Expected: $csrfToken");
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'CSRF token validation failed']);
        exit();
    }

    $streetAddress = filter_var(trim($_POST['street_address'] ?? ''), FILTER_SANITIZE_STRING);
    $city = filter_var(trim($_POST['city'] ?? ''), FILTER_SANITIZE_STRING);
    $postalCode = filter_var(trim($_POST['postal_code'] ?? ''), FILTER_SANITIZE_STRING);

    $logMessage("Add data: Street=$streetAddress, City=$city, Postal=$postalCode");

    if (empty($streetAddress) || strlen($streetAddress) > 255) {
        $logMessage("Invalid street address: $streetAddress");
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Street address required (max 255 characters)']);
        exit();
    }
    if (empty($city) || strlen($city) > 100) {
        $logMessage("Invalid city: $city");
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'City required (max 100 characters)']);
        exit();
    }
    if (empty($postalCode) || !preg_match('/^\d{5}$/', $postalCode)) {
        $logMessage("Invalid postal code: $postalCode");
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Postal code must be 5 digits']);
        exit();
    }

    try {
        $conn->begin_transaction();

        // Check for duplicate address
        $stmt = $conn->prepare("SELECT id FROM delivery_addresses WHERE customer_id = ? AND street_address = ? AND city = ? AND postal_code = ?");
        if (!$stmt) {
            $logMessage("Prepare failed for duplicate check: " . $conn->error);
            throw new Exception('Database error');
        }
        $stmt->bind_param("isss", $customerId, $streetAddress, $city, $postalCode);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $logMessage("Duplicate address detected: Street=$streetAddress, City=$city, Postal=$postalCode");
            $conn->rollback();
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'Address already exists']);
            $stmt->close();
            exit();
        }
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO delivery_addresses (customer_id, street_address, city, postal_code) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            $logMessage("Prepare failed for insert: " . $conn->error);
            throw new Exception('Database error');
        }
        $stmt->bind_param("isss", $customerId, $streetAddress, $city, $postalCode);
        if ($stmt->execute()) {
            $newAddressId = $stmt->insert_id;
            $stmt->close();
            $conn->commit();
            $logMessage("Added address ID: $newAddressId");

            $stmt = $conn->prepare("SELECT id, street_address, city, postal_code FROM delivery_addresses WHERE id = ?");
            $stmt->bind_param("i", $newAddressId);
            $stmt->execute();
            $newAddress = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            ob_end_clean();
            echo json_encode(['status' => 'success', 'message' => 'Address added successfully', 'delivery_address' => $newAddress]);
        } else {
            $logMessage("Insert failed: " . $stmt->error);
            throw new Exception('Failed to add address');
        }
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $logMessage("Add exception: " . $e->getMessage());
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit();
    }
}

// Handle editing a delivery address
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_delivery_address'])) {
    header('Content-Type: application/json');
    $logMessage("Edit request: " . json_encode($_POST));

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $logMessage("CSRF validation failed. Sent: " . ($_POST['csrf_token'] ?? 'none') . ", Expected: $csrfToken");
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'CSRF token validation failed']);
        exit();
    }

    $addressId = (int)($_POST['address_id'] ?? 0);
    $streetAddress = filter_var(trim($_POST['street_address'] ?? ''), FILTER_SANITIZE_STRING);
    $city = filter_var(trim($_POST['city'] ?? ''), FILTER_SANITIZE_STRING);
    $postalCode = filter_var(trim($_POST['postal_code'] ?? ''), FILTER_SANITIZE_STRING);

    $logMessage("Edit data: Address ID=$addressId, Street=$streetAddress, City=$city, Postal=$postalCode");

    if ($addressId <= 0) {
        $logMessage("Invalid address ID: $addressId");
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Invalid address ID']);
        exit();
    }

    if (empty($streetAddress) || strlen($streetAddress) > 255) {
        $logMessage("Invalid street address: $streetAddress");
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Street address required (max 255 characters)']);
        exit();
    }
    if (empty($city) || strlen($city) > 100) {
        $logMessage("Invalid city: $city");
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'City required (max 100 characters)']);
        exit();
    }
    if (empty($postalCode) || !preg_match('/^\d{5}$/', $postalCode)) {
        $logMessage("Invalid postal code: $postalCode");
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Postal code must be 5 digits']);
        exit();
    }

    try {
        $conn->begin_transaction();

        // Check for duplicate address (excluding current address)
        $stmt = $conn->prepare("SELECT id FROM delivery_addresses WHERE customer_id = ? AND street_address = ? AND city = ? AND postal_code = ? AND id != ?");
        if (!$stmt) {
            $logMessage("Prepare failed for duplicate check: " . $conn->error);
            throw new Exception('Database error');
        }
        $stmt->bind_param("isssi", $customerId, $streetAddress, $city, $postalCode, $addressId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $logMessage("Duplicate address detected: Street=$streetAddress, City=$city, Postal=$postalCode");
            $conn->rollback();
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'Address already exists']);
            $stmt->close();
            exit();
        }
        $stmt->close();

        $stmt = $conn->prepare("UPDATE delivery_addresses SET street_address = ?, city = ?, postal_code = ? WHERE id = ? AND customer_id = ?");
        if (!$stmt) {
            $logMessage("Prepare failed for update: " . $conn->error);
            throw new Exception('Database error');
        }
        $stmt->bind_param("sssii", $streetAddress, $city, $postalCode, $addressId, $customerId);
        if ($stmt->execute()) {
            $conn->commit();
            $logMessage("Updated address ID: $addressId");

            $stmt = $conn->prepare("SELECT id, street_address, city, postal_code FROM delivery_addresses WHERE id = ?");
            $stmt->bind_param("i", $addressId);
            $stmt->execute();
            $updatedAddress = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            ob_end_clean();
            echo json_encode(['status' => 'success', 'message' => 'Address updated successfully', 'delivery_address' => $updatedAddress]);
        } else {
            $logMessage("Update failed: " . $stmt->error);
            throw new Exception('Failed to update address');
        }
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $logMessage("Edit exception: " . $e->getMessage());
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit();
    }
}

// Handle deleting a delivery address
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_delivery_address'])) {
    header('Content-Type: application/json');
    $logMessage("Delete request: " . json_encode($_POST));

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $logMessage("CSRF validation failed. Sent: " . ($_POST['csrf_token'] ?? 'none') . ", Expected: $csrfToken");
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'CSRF token validation failed']);
        exit();
    }

    $addressId = (int)($_POST['address_id'] ?? 0);
    $logMessage("Delete address ID: $addressId");

    if ($addressId <= 0) {
        $logMessage("Invalid address ID: $addressId");
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Invalid address ID']);
        exit();
    }

    try {
        $conn->begin_transaction();

        $stmt = $conn->prepare("SELECT id FROM delivery_addresses WHERE id = ? AND customer_id = ?");
        if (!$stmt) {
            $logMessage("Prepare failed for check: " . $conn->error);
            throw new Exception('Database error');
        }
        $stmt->bind_param("ii", $addressId, $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $logMessage("Address ID $addressId not found for customer $customerId");
            $conn->rollback();
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'Address not found']);
            exit();
        }
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM delivery_addresses WHERE id = ? AND customer_id = ?");
        if (!$stmt) {
            $logMessage("Prepare failed for delete: " . $conn->error);
            throw new Exception('Database error');
        }
        $stmt->bind_param("ii", $addressId, $customerId);
        if ($stmt->execute()) {
            $conn->commit();
            $logMessage("Deleted address ID: $addressId");
            ob_end_clean();
            echo json_encode(['status' => 'success', 'message' => 'Address deleted successfully']);
        } else {
            $logMessage("Delete failed: " . $stmt->error);
            throw new Exception('Failed to delete address');
        }
        $stmt->close();
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $logMessage("Delete exception: " . $e->getMessage());
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Addresses - Brizo Fast Food Melaka</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .card-hover {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .message {
            transition: opacity 0.3s ease-in-out;
        }
        .invalid {
            border-color: #ff4757 !important;
            background-color: #fff5f5;
        }
        .spinner {
            display: none;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #ff4757;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            margin-left: 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .btn-primary {
            background-color: #ff4757;
            color: white;
        }
        .btn-primary:hover:not(:disabled) {
            background-color: #e63e4d;
        }
        .btn-primary:disabled {
            background-color: #d1d5db;
            cursor: not-allowed;
        }
        .text-primary {
            color: #ff4757;
        }
        .text-primary:hover {
            color: #e63946;
        }
        .bg-error {
            background-color: #fff5f5;
            color: #ff4757;
        }
        .bg-success {
            background-color: #f0fdf4;
            color: #15803d;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-content {
            background-color: white;
            padding: 24px;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
        }
    </style>
</head>
<body class="bg-gray-100">
    <header class="sticky top-0 bg-white shadow z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-primary">Brizo Fast Food Melaka</h1>
            <a href="http://localhost/Online-Fast-Food/customer/menu/menu.php" class="text-primary hover:text-primary flex items-center" aria-label="Return to menu page">
                <i class="fas fa-utensils mr-2" aria-hidden="true"></i> Back to Menu
            </a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-lg shadow-lg p-6 fade-in">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Manage Delivery Addresses</h2>

            <div id="message" class="message hidden p-4 rounded-lg mb-6"></div>

            <section class="mb-8">
                <h3 class="text-xl font-medium text-gray-700 mb-4">Saved Delivery Addresses</h3>
                <?php if (empty($deliveryAddresses)): ?>
                    <p class="text-gray-600">No delivery addresses saved.</p>
                <?php else: ?>
                    <div class="space-y-4" id="addressList">
                        <?php foreach ($deliveryAddresses as $address): ?>
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg card-hover" data-address-id="<?php echo htmlspecialchars($address['id']); ?>">
                                <div class="flex items-center">
                                    <i class="fas fa-home text-primary mr-3"></i>
                                    <span class="text-gray-700"><?php echo htmlspecialchars($address['street_address'] . ', ' . $address['city'] . ', ' . $address['postal_code']); ?></span>
                                </div>
                                <div class="flex space-x-2">
                                    <button onclick="openEditModal(<?php echo htmlspecialchars($address['id']); ?>, '<?php echo addslashes(htmlspecialchars($address['street_address'])); ?>', '<?php echo addslashes(htmlspecialchars($address['city'])); ?>', '<?php echo htmlspecialchars($address['postal_code']); ?>')" class="text-blue-600 hover:text-blue-800" aria-label="Edit Address">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteAddress(<?php echo htmlspecialchars($address['id']); ?>)" class="text-red-600 hover:text-red-800" aria-label="Delete Address">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="bg-gray-50 p-6 rounded-lg">
                <h3 class="text-xl font-medium text-gray-700 mb-4">Add New Delivery Address</h3>
                <form id="addAddressForm" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="text" id="streetAddress" name="street_address" placeholder="Street Address" maxlength="255" class="w-full p-3 border border-gray-300 rounded-lg" aria-label="Street Address">
                    <input type="text" id="city" name="city" placeholder="City" maxlength="100" class="w-full p-3 border border-gray-300 rounded-lg" aria-label="City">
                    <input type="text" id="postalCode" name="postal_code" placeholder="Postal Code (5 digits)" maxlength="5" oninput="formatPostalCode(this)" class="w-full p-3 border border-gray-300 rounded-lg" aria-label="Postal Code">
                    <button type="button" id="addAddressButton" onclick="addAddress()" class="w-full p-3 rounded-lg btn-primary disabled:bg-gray-400 disabled:cursor-not-allowed">
                        Add Delivery Address
                        <span class="spinner" id="addAddressSpinner"></span>
                    </button>
                </form>
            </section>
        </div>
    </main>

    <div id="editAddressModal" class="modal">
        <div class="modal-content">
            <h3 class="text-xl font-medium text-gray-700 mb-4">Edit Delivery Address</h3>
            <form id="editAddressForm" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" id="editAddressId" name="address_id">
                <input type="text" id="editStreetAddress" name="street_address" placeholder="Street Address" maxlength="255" class="w-full p-3 border border-gray-300 rounded-lg" aria-label="Street Address">
                <input type="text" id="editCity" name="city" placeholder="City" maxlength="100" class="w-full p-3 border border-gray-300 rounded-lg" aria-label="City">
                <input type="text" id="editPostalCode" name="postal_code" placeholder="Postal Code (5 digits)" maxlength="5" oninput="formatPostalCode(this)" class="w-full p-3 border border-gray-300 rounded-lg" aria-label="Postal Code">
                <div class="flex space-x-4">
                    <button type="button" id="editAddressButton" onclick="editAddress()" class="w-full p-3 rounded-lg btn-primary disabled:bg-gray-400 disabled:cursor-not-allowed">
                        Save Changes
                        <span class="spinner" id="editAddressSpinner"></span>
                    </button>
                    <button type="button" onclick="closeEditModal()" class="w-full p-3 rounded-lg bg-gray-600 text-white hover:bg-gray-700">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const addAddressForm = document.getElementById('addAddressForm');
        const addAddressButton = document.getElementById('addAddressButton');
        const addAddressSpinner = document.getElementById('addAddressSpinner');
        const editAddressForm = document.getElementById('editAddressForm');
        const editAddressButton = document.getElementById('editAddressButton');
        const editAddressSpinner = document.getElementById('editAddressSpinner');
        const editAddressModal = document.getElementById('editAddressModal');
        const messageDiv = document.getElementById('message');

        function logDebug(message) {
            console.log(`${new Date().toLocaleString()}: ${message}`);
        }

        function formatPostalCode(input) {
            input.value = input.value.replace(/\D/g, '').substring(0, 5);
        }

        function validateForm(showErrors = false) {
            const streetAddress = document.getElementById('streetAddress').value.trim();
            const city = document.getElementById('city').value.trim();
            const postalCode = document.getElementById('postalCode').value;

            const isStreetValid = streetAddress && streetAddress.length <= 255;
            const isCityValid = city && city.length <= 100;
            const isPostalValid = /^\d{5}$/.test(postalCode);

            if (showErrors) {
                document.getElementById('streetAddress').classList.toggle('invalid', !isStreetValid);
                document.getElementById('city').classList.toggle('invalid', !isCityValid);
                document.getElementById('postalCode').classList.toggle('invalid', !isPostalValid);
            }

            return isStreetValid && isCityValid && isPostalValid;
        }

        function validateEditForm(showErrors = false) {
            const streetAddress = document.getElementById('editStreetAddress').value.trim();
            const city = document.getElementById('editCity').value.trim();
            const postalCode = document.getElementById('editPostalCode').value;

            const isStreetValid = streetAddress && streetAddress.length <= 255;
            const isCityValid = city && city.length <= 100;
            const isPostalValid = /^\d{5}$/.test(postalCode);

            if (showErrors) {
                document.getElementById('editStreetAddress').classList.toggle('invalid', !isStreetValid);
                document.getElementById('editCity').classList.toggle('invalid', !isCityValid);
                document.getElementById('editPostalCode').classList.toggle('invalid', !isPostalValid);
            }

            return isStreetValid && isCityValid && isPostalValid;
        }

        function showMessage(type, message) {
            messageDiv.classList.remove('hidden', 'bg-error', 'bg-success');
            messageDiv.classList.add(type === 'success' ? 'bg-success' : 'bg-error');
            messageDiv.textContent = message;
            logDebug(`Message: ${type} - ${message}`);
            setTimeout(() => messageDiv.classList.add('hidden'), 5000);
        }

        function resetAddForm() {
            addAddressForm.reset();
            document.querySelectorAll('#addAddressForm .invalid').forEach(el => el.classList.remove('invalid'));
            addAddressButton.disabled = false;
        }

        function addAddress() {
            logDebug('Add address button clicked');
            document.querySelectorAll('#addAddressForm .invalid').forEach(el => el.classList.remove('invalid'));

            if (!validateForm(true)) {
                showMessage('error', 'Please fill in all fields correctly');
                return;
            }

            addAddressButton.disabled = true;
            addAddressSpinner.style.display = 'inline-block';

            const formData = new FormData(addAddressForm);
            formData.append('add_delivery_address', '1');

            const formDataEntries = {};
            for (let [key, value] of formData.entries()) formDataEntries[key] = value;
            logDebug('Add FormData: ' + JSON.stringify(formDataEntries));

            fetch('delivery_addresses.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                logDebug(`Add Response status: ${response.status}`);
                if (!response.ok) throw new Error(`Network error: ${response.status} - ${response.statusText}`);
                return response.json();
            })
            .then(data => {
                logDebug('Add Response: ' + JSON.stringify(data));
                if (data.status === 'success') {
                    showMessage('success', data.message);
                    const address = data.delivery_address;
                    const addressList = document.getElementById('addressList') || document.createElement('div');
                    if (!addressList.id) {
                        addressList.id = 'addressList';
                        addressList.classList.add('space-y-4');
                        document.querySelector('section.mb-8').appendChild(addressList);
                        document.querySelector('section.mb-8 p')?.remove();
                    }
                    const addressDiv = document.createElement('div');
                    addressDiv.className = 'flex items-center justify-between p-4 bg-gray-50 rounded-lg card-hover';
                    addressDiv.dataset.addressId = address.id;
                    addressDiv.innerHTML = `
                        <div class="flex items-center">
                            <i class="fas fa-home text-primary mr-3"></i>
                            <span class="text-gray-700">${escapeHtml(address.street_address + ', ' + address.city + ', ' + address.postal_code)}</span>
                        </div>
                        <div class="flex space-x-2">
                            <button onclick="openEditModal(${address.id}, '${escapeHtml(address.street_address).replace(/'/g, "\\'")}', '${escapeHtml(address.city).replace(/'/g, "\\'")}', '${address.postal_code}')" class="text-blue-600 hover:text-blue-800" aria-label="Edit Address">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteAddress(${address.id})" class="text-red-600 hover:text-red-800" aria-label="Delete Address">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `;
                    addressList.prepend(addressDiv);
                    resetAddForm();
                } else {
                    showMessage('error', data.message || 'Failed to add address');
                }
            })
            .catch(error => {
                logDebug('Add error: ' + error.message);
                console.error('Add address error:', error);
                showMessage('error', 'Error adding address: ' + error.message);
            })
            .finally(() => {
                addAddressButton.disabled = false;
                addAddressSpinner.style.display = 'none';
            });
        }

        function openEditModal(id, streetAddress, city, postalCode) {
            document.getElementById('editAddressId').value = id;
            document.getElementById('editStreetAddress').value = streetAddress;
            document.getElementById('editCity').value = city;
            document.getElementById('editPostalCode').value = postalCode;
            document.querySelectorAll('#editAddressForm .invalid').forEach(el => el.classList.remove('invalid'));
            editAddressModal.style.display = 'flex';
            editAddressButton.disabled = false;
            logDebug(`Edit modal opened for ID: ${id}`);
        }

        function closeEditModal() {
            editAddressModal.style.display = 'none';
            editAddressForm.reset();
            document.querySelectorAll('#editAddressForm .invalid').forEach(el => el.classList.remove('invalid'));
            logDebug('Edit modal closed');
        }

        function editAddress() {
            logDebug('Edit address button clicked');
            document.querySelectorAll('#editAddressForm .invalid').forEach(el => el.classList.remove('invalid'));

            if (!validateEditForm(true)) {
                showMessage('error', 'Please fill in all fields correctly');
                return;
            }

            editAddressButton.disabled = true;
            editAddressSpinner.style.display = 'inline-block';

            const formData = new FormData(editAddressForm);
            formData.append('edit_delivery_address', '1');

            const formDataEntries = {};
            for (let [key, value] of formData.entries()) formDataEntries[key] = value;
            logDebug('Edit FormData: ' + JSON.stringify(formDataEntries));

            fetch('delivery_addresses.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                logDebug(`Edit Response status: ${response.status}`);
                if (!response.ok) throw new Error(`Network error: ${response.status} - ${response.statusText}`);
                return response.json();
            })
            .then(data => {
                logDebug('Edit Response: ' + JSON.stringify(data));
                if (data.status === 'success') {
                    showMessage('success', data.message);
                    const address = data.delivery_address;
                    const addressDiv = document.querySelector(`[data-address-id="${address.id}"]`);
                    addressDiv.innerHTML = `
                        <div class="flex items-center">
                            <i class="fas fa-home text-primary mr-3"></i>
                            <span class="text-gray-700">${escapeHtml(address.street_address + ', ' + address.city + ', ' + address.postal_code)}</span>
                        </div>
                        <div class="flex space-x-2">
                            <button onclick="openEditModal(${address.id}, '${escapeHtml(address.street_address).replace(/'/g, "\\'")}', '${escapeHtml(address.city).replace(/'/g, "\\'")}', '${address.postal_code}')" class="text-blue-600 hover:text-blue-800" aria-label="Edit Address">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteAddress(${address.id})" class="text-red-600 hover:text-red-800" aria-label="Delete Address">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `;
                    closeEditModal();
                } else {
                    showMessage('error', data.message || 'Failed to update address');
                }
            })
            .catch(error => {
                logDebug('Edit error: ' + error.message);
                console.error('Edit address error:', error);
                showMessage('error', 'Error updating address: ' + error.message);
            })
            .finally(() => {
                editAddressButton.disabled = false;
                editAddressSpinner.style.display = 'none';
            });
        }

        function deleteAddress(id) {
            logDebug(`Delete address button clicked for ID: ${id}`);
            if (!confirm('Are you sure you want to delete this address?')) return;

            const formData = new FormData();
            formData.append('delete_delivery_address', '1');
            formData.append('address_id', id);
            formData.append('csrf_token', '<?php echo addslashes(htmlspecialchars($csrfToken)); ?>');

            const formDataEntries = {};
            for (let [key, value] of formData.entries()) formDataEntries[key] = value;
            logDebug('Delete FormData: ' + JSON.stringify(formDataEntries));

            fetch('delivery_addresses.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                logDebug(`Delete Response status: ${response.status}`);
                if (!response.ok) throw new Error(`Network error: ${response.status} - ${response.statusText}`);
                return response.json();
            })
            .then(data => {
                logDebug('Delete Response: ' + JSON.stringify(data));
                if (data.status === 'success') {
                    showMessage('success', data.message);
                    const addressDiv = document.querySelector(`[data-address-id="${id}"]`);
                    addressDiv.remove();
                    if (!document.querySelector('#addressList')?.children.length) {
                        document.querySelector('section.mb-8').innerHTML = '<p class="text-gray-600">No delivery addresses saved.</p>';
                    }
                } else {
                    showMessage('error', data.message || 'Failed to delete address');
                }
            })
            .catch(error => {
                logDebug('Delete error: ' + error.message);
                console.error('Delete address error:', error);
                showMessage('error', 'Error deleting address: ' + error.message);
            });
        }

        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>
</body>
</html>
<!-- Menu Icon -->
<div class="menu-container">
    <div class="menu-icon" onclick="toggleMenu()" title="Menu Icon">
        <span></span>
        <span></span>
        <span></span>
    </div>

    <nav class="dropdown-menu">
        <a href="/Online-Fast-Food/customer/menu/menu.php">Home</a>
        <a href="../manage_account/profile.php">Profile</a>
                <a href="/Online-Fast-Food/customer/orders/orders.php">My Orders</a>
        <a href="/Online-Fast-Food/payment/brizo-fast-food-payment/payment_history.php">Payment History</a>
        <a href="/Online-Fast-Food/payment/brizo-fast-food-payment/feedback.php">Feedback</a>

        <?php if (isset($_SESSION['customer_id'])): ?>
        <a href="#" class="btn-logout-animated">Log out</a>
        <?php else: ?>
        <a href="/Online-Fast-Food/customer/login.php" class="btn-login">Login</a>
        <?php endif; ?>
    </nav>
</div>

<!-- 🔒 Custom Logout Modal -->
<div id="logoutModal" class="logout-modal hidden">
  <div class="logout-box">
    <p>Are you sure you want to log out?</p>
    <div class="logout-actions">
      <button id="confirmLogout">Yes, log out</button>
      <button id="cancelLogout">Cancel</button>
    </div>
  </div>
</div>


    <style>
    .menu-container {
        position: fixed;
        top: 18px;
        left: 24px;
        z-index: 1000;
    }

    .menu-icon {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: #ff4757;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        transition: all 0.3s ease;
    }

    .menu-icon span {
        position: absolute;
        height: 3px;
        width: 24px;
        background: #fff;
        border-radius: 3px;
        transition: all 0.3s ease;
    }

    .menu-icon:hover {
  background-color: #b92f2f;
}

    .menu-icon span:nth-child(1) { top: 16px; }
    .menu-icon span:nth-child(2) { top: 24px; }
    .menu-icon span:nth-child(3) { top: 32px; }

    .menu-icon.active span:nth-child(1) {
        transform: rotate(45deg) translate(5px, 5px);
    }

    .menu-icon.active span:nth-child(2) {
        opacity: 0;
    }

    .menu-icon.active span:nth-child(3) {
        transform: rotate(-45deg) translate(5px, -5px);
    }

    .dropdown-menu {
        display: none;
        position: absolute;
        top: 62px;
        left: 0;
        background: rgba(255, 250, 250, 0.63);
        backdrop-filter: blur(10px);
        border-radius: 12px;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
        padding: 8px 0;
        border: 1px solid rgba(255, 255, 255, 0.34);
        min-width: 180px;
        transition: all 0.3s ease;
    }

    .dropdown-menu.active {
        display: block;
        animation: slideDown 0.3s ease;
    }

    .dropdown-menu a {
        display: block;
        padding: 12px 20px;
        text-decoration: none;
        color: #222;
        font-weight: 500;
        transition: background 0.3s ease;
    }

    .dropdown-menu a:hover {
        background: rgba(255, 157, 157, 0.55);
    }


    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .logout-modal {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    transition: opacity 0.3s ease;
    }

    .logout-modal.hidden {
    opacity: 0;
    pointer-events: none;
    }

    .logout-box {
    background: #fff;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    animation: slideUp 0.4s ease forwards;
    max-width: 320px;
    width: 100%;
    text-align: center;
    font-family: 'Lexend', sans-serif;
    }

    .logout-actions {
    display: flex;
    justify-content: space-around;
    margin-top: 1.5rem;
    }

    .logout-actions button {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    }

    #confirmLogout {
    background-color: #d63031;
    color: white;
    }

    #cancelLogout {
    background-color: #b2bec3;
    color: #2d3436;
    }

    @keyframes slideUp {
    from {
        transform: translateY(60px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
    }

    </style>

    <script>

    document.addEventListener("DOMContentLoaded", () => {
    const logoutBtn = document.querySelector(".btn-logout-animated");
    const modal = document.getElementById("logoutModal");
    const confirmBtn = document.getElementById("confirmLogout");
    const cancelBtn = document.getElementById("cancelLogout");

    logoutBtn?.addEventListener("click", e => {
        e.preventDefault();
        modal.classList.remove("hidden");
    });

    confirmBtn?.addEventListener("click", () => {
        window.location.href = "/Online-Fast-Food/customer/logout.php";
    });

    cancelBtn?.addEventListener("click", () => {
        modal.classList.add("hidden");
    });
    });

    function toggleMenu() {
        const icon = document.querySelector('.menu-icon');
        const menu = document.querySelector('.dropdown-menu');
        
        icon.classList.toggle('active');
        menu.classList.toggle('active');
    }



    </script>
