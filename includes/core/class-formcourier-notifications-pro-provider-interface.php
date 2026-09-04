<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

interface FormCourier_Notifications_Pro_Provider_Interface {
    public function get_id(): string;
    public function get_name(): string;
    public function send( FormCourier_Notifications_Pro_Submission $submission, array $context = [] ): array;
}
