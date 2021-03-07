<?php

class Payment_Slip_Data {
    private $format = 'HRVHUB30';
    public $currency;
    private $price;
    public $sender_name;
    public $sender_address;
    public $sender_city;
    public $recipient_name;
    public $recipient_address;
    public $recipient_city;
    public $recipient_iban;
    public $payment_model;
    public $recipient_callout_number;
    public $intent_code;
    public $description;

    public function get_price() {
        return sprintf('%.2f', $this->price / 100);
    }

    public function set_price($price) {
        $full_length = 15;
        $this->price = str_pad(round($price * 100), $full_length, '0', STR_PAD_LEFT);
    }

    private function get_delimiter() {
        return chr(0x0A);
    }

    public function encode() {
        return implode($this->get_delimiter(), array_map(function($value) {
            return implode(' ', array_map(function($line) {
                return trim($line);
            }, explode($this->get_delimiter(), $value)));
        }, array_values(get_object_vars($this))));
    }
}
