<?php
session_start();

// 세션 전체 제거
session_destroy();

header("Location: /");
exit;