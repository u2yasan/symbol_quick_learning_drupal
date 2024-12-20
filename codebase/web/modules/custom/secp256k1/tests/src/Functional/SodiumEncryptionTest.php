<?php

namespace Drupal\Tests\sodium\Functional;

use Drupal\Component\Utility\Random;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\key\Entity\Key;

/**
 * Tests encryption and decryption with the Sodium encryption method.
 *
 * @group sodium
 */
class SodiumEncryptionTest extends BrowserTestBase {

  use StringTranslationTrait;

  const TEST_ID = 'sodium_test';

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['key', 'encrypt', 'sodium'];

  /**
   * An administrator user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'administer encrypt',
      'administer keys',
    ]);

    $this->drupalLogin($this->adminUser);

    // Create a key to use for testing.
    $this->createTestKey();
  }

  /**
   * Test encryption and decryption with the Sodium encryption method.
   */
  public function testEncryptAndDecrypt(): void {
    // Go to the page to add an encryption profile.
    $this->drupalGet('admin/config/system/encryption/profiles/add');

    // Check that the Sodium encryption method is available.
    $this->assertSession()->optionExists('edit-encryption-method', 'sodium');
    $this->assertSession()->pageTextContains('Sodium');

    // Submit the form.
    $form_values = [
      'id' => self::TEST_ID,
      'label' => 'Sodium test',
      'encryption_method' => 'sodium',
      'encryption_key' => self::TEST_ID,
    ];
    $this->submitForm($form_values, 'Save');

    // Confirm that the encryption profile was successfully saved.
    $encryption_profile = \Drupal::entityTypeManager()->getStorage('encryption_profile')->load(self::TEST_ID);
    $this->assertTrue(isset($encryption_profile), 'Test encryption profile was successfully saved.');

    // Create random text to use for testing.
    $random = new Random();
    $test_plaintext = $random->string(20);

    // Encrypt the test text and confirm it's different from the plaintext.
    $encrypted_text = \Drupal::service('encryption')->encrypt($test_plaintext, $encryption_profile);
    $this->assertNotEquals($test_plaintext, $encrypted_text, 'The test text was successfully encrypted.');

    // Decrypt the encrypted text and confirm it's the same as the plaintext.
    $decrypted_text = \Drupal::service('encryption')->decrypt($encrypted_text, $encryption_profile);
    $this->assertEquals($test_plaintext, $decrypted_text, 'The test text was successfully decrypted.');
  }

  /**
   * Creates a test key to use for encryption and decryption.
   */
  protected function createTestKey(): void {
    $key_config = [
      'id' => self::TEST_ID,
      'label' => 'Sodium test',
      'description' => 'A test key for the Sodium encryption method.',
      'key_type' => 'encryption',
      'key_type_settings' => [
        'key_size' => '256',
      ],
      'key_provider' => 'config',
      'key_provider_settings' => [
        'key_value' => '12345678901234567890123456789012',
        'base64_encoded' => FALSE,
      ],
      'key_input' => 'text_field',
      'key_input_settings' => [
        'base64_encoded' => FALSE,
      ],
    ];

    // Create a test key and save it.
    $key = new Key($key_config, 'key');
    $key->save();
  }

}
