<?php
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
header('Location: ' . ($id ? 'order.php?id=' . $id : 'index.php'), true, 301);
