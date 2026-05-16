<?php

return [

    'HR' => [

        [
            'title' => 'Dashboard',
            'icon'  => 'dashboard',
            'url'   => BASE_URL . 'views/hr/dashboard.php',
        ],

        [
            'title' => 'Activity Logs',
            'icon'  => 'description',
            'url'   => BASE_URL . 'views/hr/activity_logs.php',
        ],

    ],

    'ACCOUNTING' => [

        [
            'title' => 'Dashboard',
            'icon'  => 'dashboard',
            'url'   => BASE_URL . 'views/accounting/dashboard.php',
        ],

        [
            'title' => 'Payroll',
            'icon'  => 'payments',

            'children' => [

                [
                    'title' => 'Payroll Table',
                    'icon'  => 'table_view',
                    'url'   => BASE_URL . 'views/accounting/payroll_table.php',
                ],

                [
                    'title' => 'Payroll Masterlist',
                    'icon'  => 'account_balance',
                    'url'   => BASE_URL . 'views/accounting/sss_contribution.php',
                ],

            ]
        ],

        [
            'title' => 'Contribution Table',
            'icon'  => 'account_balance',

            'children' => [

                [
                    'title' => 'SSS Bracket',
                    'icon'  => 'table_view',
                    'url'   => BASE_URL . 'views/accounting/sss_bracket_table.php',
                ],

                [
                    'title' => 'Philhealth',
                    'icon'  => 'health_and_safety',
                    'url'   => BASE_URL . 'views/accounting/philhealth_contribution.php',
                ],

                [
                    'title' => 'PAG-IBIG',
                    'icon'  => 'savings',
                    'url'   => BASE_URL . 'views/accounting/pagibig_contribution.php',
                ],

            ]
        ],

        [
            'title' => 'Employees',
            'icon'  => 'badge',
            'url'   => BASE_URL . 'views/accounting/employees.php',
        ],

		[
			'title' => 'Daily Time Record',
			'icon'  => 'schedule',
			'url'   => BASE_URL . 'views/accounting/daily_time_record.php',
		],

        [
            'title' => 'Calendar',
            'icon'  => 'calendar_month',
            'url'   => BASE_URL . 'views/accounting/calendar.php',
        ],

        [
            'title' => 'Activity Logs',
            'icon'  => 'description',
            'url'   => BASE_URL . 'views/accounting/activity_logs.php',
        ],

    ]

];