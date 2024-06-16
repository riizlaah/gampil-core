<?php
namespace Gampil\Utils;

use Exception;

class SCrypto {
  public function rbytes($length) {
    return random_bytes($length);
  }
  public function encryptaes256_data($data, $key) {
    $nonce = $this->rbytes(SODIUM_CRYPTO_AEAD_AES256GCM_KEYBYTES);
    $cipher = sodium_crypto_aead_aes256gcm_encrypt($data, '', $nonce, $key);
    return base64_encode($nonce . $cipher);
  }
  public function decryptaes256_data($encrypted_data, $key) {
    $decoded = base64_decode($encrypted_data);
    $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES, '8bit');
    $cypher = mb_substr($decoded, SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES, null, '8bit');
    $data = sodium_crypto_aead_aes256gcm_decrypt($cypher, '', $nonce, $key);
    return $data;
  }
}