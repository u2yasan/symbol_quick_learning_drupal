<?php

namespace Drupal\secp256k1\Plugin\EncryptionMethod;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\encrypt\Attribute\EncryptionMethod;
use Drupal\encrypt\EncryptionMethodInterface;
use Drupal\encrypt\Exception\EncryptException;
use Drupal\encrypt\Plugin\EncryptionMethod\EncryptionMethodBase;
// use ParagonIE\Halite\Alerts\HaliteAlert;
// use ParagonIE\Halite\Alerts\InvalidKey;
// use ParagonIE\Halite\Symmetric\Crypto;
// use ParagonIE\Halite\Symmetric\EncryptionKey;
// use ParagonIE\HiddenString\HiddenString;
use Drupal\secp256k1\HiddenString;
use phpseclib3\Crypt\EC;
use phpseclib3\Crypt\EC\Curves\secp256k1;
use phpseclib3\Crypt\Random;

/**
 * Adds an encryption method that uses secp256k1 for cryptographic operations.
 *
 * @EncryptionMethod(
 *   id = "secp256k1",
 *   title = @Translation("secp256k1"),
 *   description = "Uses secp256k1 for cryptographic operations.",
 *   key_type = {"encryption"}
 * )
 */
#[EncryptionMethod(
  id: "secp256k1",
  title: new TranslatableMarkup("secp256k1"),
  description: new TranslatableMarkup("Uses secp256k1 for cryptographic operations."),
  key_type: ["encryption"]
)]
class Secp256k1EncryptionMethod extends EncryptionMethodBase implements EncryptionMethodInterface {

  /**
   * {@inheritdoc}
   */
  public function checkDependencies($text = NULL, $key = NULL): array {
    $errors = [];

    if (!class_exists('\phpseclib3\Crypt\EC')) {
      $errors[] = $this->t('phpseclib3 PHP library is not installed.');
    }

    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function encrypt($text, $key): string {
    // Create the key object.
    $key_hidden = new HiddenString($key);
    // Encrypt the data.  
    $text_hidden = new HiddenString($text);
    // $encrypted_data = Crypto::encrypt($text_hidden-get(), $key_hidden->get(), TRUE);
    // Create EC instance with secp256k1 curve
    $ec = new EC('secp256k1');
    $privateKey = $ec->loadPrivateKey($key_hidden->get());
    $publicKey = $privateKey->getPublicKey();

    // Encrypt the data
    $encrypted_data = $publicKey->encrypt($text_hidden->get());

    unset($key_hidden);
    unset($text_hidden);

    return $encrypted_data;
  }

  /**
   * {@inheritdoc}
   */
  public function decrypt($text, $key): string {
    // Create the key object.
    $key_hidden = new HiddenString($key);
   
    // Create EC instance with secp256k1 curve
    $ec = new EC('secp256k1');
    $privateKey = $ec->loadPrivateKey($key_hidden->get());

    // Decrypt the data
    $decrypted_data = $privateKey->decrypt($text);
    unset($key_hidden);

    return $decrypted_data;
  }

  /**
   * Validate the private key with passphrase.
   *
   * @param string $privateKey
   * @param string $passphrase
   * @return bool
   */
  // public function validatePrivateKeyWithPassphrase(string $privateKey, string $passphrase): bool {
  //   try {
  //       // Create the key object with passphrase.
  //       $key_hidden = new HiddenString($privateKey);
  //       $passphrase_hidden = new HiddenString($passphrase);

  //       // Load the private key with passphrase
  //       $ec = EC::loadFormat('PKCS8', $key_hidden->get(), $passphrase_hidden->get());

  //       // If no exception is thrown, the key is valid
  //       return true;
  //   } catch (\Exception $e) {
  //       // Log the error message if needed
  //       \Drupal::logger('key')->error('112 Error validating value for key: ' . $e->getMessage());
  //       return false;
  //   }
  // }

}
