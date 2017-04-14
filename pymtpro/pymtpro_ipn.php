<?php
$post_body = json_decode(file_get_contents("php://input"));
if (isset($post_body->order)) {
	$PYMTPro_order  = $post_body->order;
	$order_id       = $PYMTPro_order->custom;
	$order          = $PYMTPro_order->name;
} else if (isset($post_body->payout)) {
	header('HTTP/1.1 200 OK');
	exit("PYMTPro Payout Callback Ignored");
} else {
	header("HTTP/1.1 400 Bad Request");
	exit("Unrecognized PYMTPro Callback");
}