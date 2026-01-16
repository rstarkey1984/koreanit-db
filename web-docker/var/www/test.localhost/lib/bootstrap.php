<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!isset($pageTitle)) {
  $pageTitle = "웹 페이지";
}
?>