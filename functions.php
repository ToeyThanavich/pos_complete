<?php
function money($n){ return number_format((float)$n, 2); }

function generate_order_code($length = 8){
  $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  $code = '';
  for ($i=0;$i<$length;$i++){
    $code .= $chars[random_int(0, strlen($chars)-1)];
  }
  return $code;
}
?>