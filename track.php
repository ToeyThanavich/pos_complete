<?php
// Redirect to order_status.php preserving query
header("Location: order_status.php?" . http_build_query($_GET));
exit;
