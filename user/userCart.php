<?php
require_once '../database/database.php';

session_start();
// Database connection
$db_host = "127.0.0.1";
$db_username = "root";
$db_password = "";
$db_name = "peasy";
$db_port = 3306;

$conn = new mysqli($db_host, $db_username, $db_password, $db_name, $db_port);
$conn = getDBConnection();
$user_id = $_SESSION['user_id'] ?? '';
$firstname = $_SESSION['user_firstname'] ?? 'Guest';
$lastname = $_SESSION['user_lastname'] ?? 'Guest';
$username = $_SESSION['user_username'] ?? 'Guest';
$email = $_SESSION['user_email'] ?? 'Guest';
$_SESSION['profile_picture'] = $_SESSION['profile_picture'] ?? 'default.jpg';

// Add to cart
if (isset($_POST['add_to_cart'])) {
    $id = $_POST['product_id'];
    if (!isset($_SESSION['cart'][$id])) {
        $_SESSION['cart'][$id] = 1;
    } else {
        $_SESSION['cart'][$id]++;
    }
    header("Location: userCart.php");
    exit;
}

// Update quantity
if (isset($_POST['update_quantity'])) {
    $id = $_POST['product_id'];
    $qty = max(1, intval($_POST['quantity']));
    $_SESSION['cart'][$id] = $qty;
}

// Remove item
if (isset($_GET['remove'])) {
    unset($_SESSION['cart'][$_GET['remove']]);
}

// Clear cart
if (isset($_GET['clear'])) {
    unset($_SESSION['cart']);
}

$cartItems = [];
$total = 0;

if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $ids = implode(",", array_map('intval', array_keys($_SESSION['cart'])));
    $result = $conn->query("SELECT * FROM products WHERE id IN ($ids)");

    while ($row = $result->fetch_assoc()) {
        $productId = $row['id'];
        $quantity = isset($_SESSION['cart'][$productId]) && is_numeric($_SESSION['cart'][$productId])
                    ? intval($_SESSION['cart'][$productId])
                    : 1;

        $row['quantity'] = $quantity;
        $row['subtotal'] = $row['price'] * $quantity;
        $cartItems[] = $row;
        $total += $row['subtotal'];
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['address'], $_POST['contact_number'])) {
    $address = $_POST['address'];
    $contact_number = $_POST['contact_number'];

    $stmt = $conn->prepare("SELECT id FROM users_profile WHERE users_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

}

if (isset($_POST['checkout']) && !empty($cartItems)) {
    $stmt = $conn->prepare("INSERT INTO orders (user_id, total, created_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("id", $user_id, $total);
    $stmt->execute();
    $order_id = $stmt->insert_id;
    $stmt->close();

    $stmtItem = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
    foreach ($cartItems as $item) {
        $stmtItem->bind_param("iiid", $order_id, $item['id'], $item['quantity'], $item['price']);
        $stmtItem->execute();
    }
    $stmtItem->close();

    unset($_SESSION['cart']);
    header("Location: printReceipt.php?order_id=" . $order_id);
    exit;
}



?>
<!DOCTYPE html>
<html>
<head>
    <title>My Cart</title>
    <link rel="stylesheet" href="../guest/index.css">
    <link rel="stylesheet" href="../guest/priceList.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body>

<nav class="navbar navbar-expand-lg bg-success px-4 py-2">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="">
                <img src="../assets/nobg.png" alt="Logo" width="60" height="60" class="me-2">
                <strong class="text-white">PEasy</strong>
            </a>
            <button class="navbar-toggler text-white" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-center">
                    <li class="nav-item mx-2">
                        <a class="nav-link text-white" href="userCart.php"><i class="bi bi-cart fs-4"></i></a>
                    </li>
                    <li class="nav-item mx-2">
                        <a class="nav-link text-white" href="printReceipt.php"><i class="bi bi-chat-left fs-4"></i></a>
                    </li>
                    <li class="nav-item mx-2 ">
                        <a class="nav-link text-white d-flex align-items-center just"
                            href="<?php echo isset($_SESSION['user_logged_in']) ? 'profile.php' : 'profile.php'; ?>">
                            <i class="bi bi-person-circle fs-4 mx-2"></i>
                            <span>
                                <?php
            if (isset($_SESSION['user_logged_in']) && isset($_SESSION['user_firstname'])) {
                echo htmlspecialchars($_SESSION['user_firstname']);
            } else {
                echo 'Login';
            }
            ?>
                            </span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
</nav>


<div class="container py-4 justify-content-end">
    <h2 class="bi bi-cart text-center align-items-center">My Cart</h2>
    <a href="index.php" class="btn btn-outline-success mb-3" style="margin-left: 79%;">Continue Shopping</a>
    <a href="?clear=1" class="btn btn-outline-danger mb-3 float-end" data-bs-toggle="modal" data-bs-target="#deleteCart">Clear Cart</a>
    <?php if (!empty($cartItems)): ?>
        <?php foreach ($cartItems as $item): ?>
            <div class="card mb-3">
                <div class="card-body d-flex align-items-center">
                    <img src="../<?= htmlspecialchars($item['image']) ?>" alt="" width="200" height="200" class="me-3">

                    <div class="flex-grow-1">
                        <h5><?= $item['name'] ?></h5>
                        <p>Price: ₱<?= number_format($item['price'], 2) ?></p>
                        <form method="post" class="d-flex justify-content-end mb-4">
                            <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                            <input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="1" class="form-control w-25 me-2">
                            <button name="update_quantity" class="btn btn-sm btn-primary" style="height: 10%;">Update</button>
                        </form>
                    </div>
                    <div class="ms-3">
                        <p><strong>Subtotal:</strong> ₱<?= number_format($item['subtotal'], 2) ?></p>
                        <a href="?remove=<?= $item['id'] ?>" class="btn btn-sm btn-danger">Remove</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="text-end">
            <h4>Total: ₱<?= number_format($total, 2) ?></h4>
            <form method="post" class="mt-3">
    <input type="hidden" name="checkout" value="1">
    <input type="text" name="address" required placeholder="Enter Address (Note: You may get banned or blacklisted if you submit fraud or false informations!)" class="form-control mb-2">
    <input type="text" name="contact_number" required placeholder="Enter Contact Number (Note: You may get banned or blacklisted if you submit fraud or  false informations!)" class="form-control mb-2">
    <button type="submit" class="btn btn-success">Proceed to Checkout</button>
</form>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">Your cart is empty.</div>
    <?php endif; ?>
</div>

<div class="modal fade" id="deleteCart" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirmation</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Your cart items will be removed, Continue?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cancel</button>
        <a href="" class="btn btn-success">
                        <i class="bi bi-check-lg me-2"></i>Continue
                    </a>
      </div>
    </div>
  </div>
</div>

<footer class="bg-dark text-white py-4 mt-5 m" >
        <div class="container" style="margin-top: 5%;">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h5>PEasy</h5>
                    <p>Your one-stop shop for all your needs.</p>
                </div>
                <div class="col-md-4 mb-3">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-white">About Us</a></li>
                        <li><a href="#" class="text-white">Contact Us</a></li>
                        <li><a href="#" class="text-white">FAQ</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-3">
                    <h5>Connect With Us</h5>
                    <div class="d-flex">
                        <a href="#" class="text-white me-3"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="text-white me-3"><i class="bi bi-twitter"></i></a>
                        <a href="#" class="text-white me-3"><i class="bi bi-instagram"></i></a>
                    </div>
                </div>
            </div>
            <hr>
            <p class="text-center mb-0">&copy; 2025 PEasy. All rights reserved.</p>
        </div>
</footer>

</body>
</html>
