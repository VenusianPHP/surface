<?php

namespace Surface\Contracts\Windows\Services;

/** The icon and weight of an alert; null on Alert is the platform's plain alert. */
enum AlertStyle: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';
}
