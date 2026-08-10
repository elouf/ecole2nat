<?php

namespace Ecole2Nat\Swimmer;

if (!defined('ABSPATH')) {
    exit;
}

final class SwimmerSearchCriteria
{
    public string $search = '';
    public int $groupId = 0;
    public int $categoryId = 0;
    public int $seasonId = 0;
    public string $status = '';
    public string $assignment = '';
    public string $orderBy = 'last_name';
    public string $order = 'asc';
    public int $page = 1;
    public int $perPage = 25;
}
