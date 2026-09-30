<?php
$id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
header('Location: ' . ($id ? 'order.php?id=' . $id : 'index.php'), true, 301);
