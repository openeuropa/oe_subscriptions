<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_subscriptions_digest\Functional;

use Drupal\decoupled_auth\Entity\DecoupledAuthUser;
use Drupal\Tests\oe_subscriptions_anonymous\Trait\AssertMailTrait;
use Drupal\user\UserInterface;

/**
 * Tests the user notifications frequency in subscriptions page.
 */
class AnonymousUserDigestTest extends UserDigestTest {

  use AssertMailTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_subscriptions_anonymous',
    'oe_subscriptions_digest',
  ];

  /**
   * Tests the anonymous user digest preference.
   */
  public function testAnonymousUserPreference(): void {
    $user = DecoupledAuthUser::create([
      'mail' => $this->randomMachineName() . '@example.com',
      'name' => NULL,
      'status' => 1,
      'roles' => ['anonymous_subscriber'],
    ]);
    $user->save();
    $fn_get_path = function (UserInterface $user) {
      return $this->getAnonymousUserSubscriptionsPageUrl($user->getEmail());
    };

    $this->doTestDigestPreferences($user, $fn_get_path);
  }

  /**
   * Retrieves the URL to access the subscriptions page for a user.
   *
   * This method will request the URL via the dedicated form.
   *
   * @param string $email
   *   The e-mail of the user.
   *
   * @return string
   *   The URL.
   */
  protected function getAnonymousUserSubscriptionsPageUrl(string $email): string {
    $this->drupalGet('/user/subscriptions');
    $assert_session = $this->assertSession();
    $assert_session->fieldExists('Your e-mail')->setValue($email);
    $assert_session->buttonExists('Submit')->press();
    $assert_session->statusMessageContains('A confirmation e-email has been sent to your e-mail address.', 'status');

    $mails = $this->getMails();
    $this->assertCount(1, $mails);
    $this->assertMailProperty('to', $email);
    $this->assertMailProperty('subject', 'Access your subscriptions page');
    $this->assertMailString('body', 'Click here to access your subscriptions page. [1]');
    $mail_urls = $this->getMailFootNoteUrls($mails[0]['body']);
    $this->assertCount(1, $mail_urls);
    $base_path = $this->getAbsoluteUrl('/user/subscriptions/' . rawurlencode($email));
    $this->assertMatchesRegularExpression('@^' . preg_quote($base_path, '@') . '/.+$@', $mail_urls[1]);
    $this->resetMailCollector();

    return $mail_urls[1];
  }

}
