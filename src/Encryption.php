<?php
// src/Encryption.php

require_once __DIR__ . '/../conf/conf.php';

class Encryption {
    private $key;
    private $cipher = "aes-256-cbc";

    public function __construct() {
        if (!defined('ENCRYPTION_KEY')) {
            throw new Exception("Encryption key not defined in configuration.");
        }
        $this->key = ENCRYPTION_KEY;
    }

    public function encrypt($data) {
        $ivlen = openssl_cipher_iv_length($this->cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext = openssl_encrypt($data, $this->cipher, $this->key, $options=0, $iv);
        return base64_encode($iv . $ciphertext);
    }

    public function decrypt($data) {
        $c = base64_decode($data);
        $ivlen = openssl_cipher_iv_length($this->cipher);
        $iv = substr($c, 0, $ivlen);
        $ciphertext = substr($c, $ivlen);
        return openssl_decrypt($ciphertext, $this->cipher, $this->key, $options=0, $iv);
    }
}
