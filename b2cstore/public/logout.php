<?php
/* Copyright (C) 2025 Henaxis */
include 'main.inc.php';

/** @var B2CStoreContext $context */
$context->destroySession();

header('Location: index.php?controller=home');
exit;
