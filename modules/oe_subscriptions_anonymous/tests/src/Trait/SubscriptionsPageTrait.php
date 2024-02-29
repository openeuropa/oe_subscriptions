<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_subscriptions_anonymous\Trait;

use Drupal\Core\Url;

/**
 * Trait to retrieve URL for anonymous subscrtipions page.
 */
trait SubscriptionsPageTrait {

  use AssertMailTrait;
  use StatusMessageTrait;

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
    $this->assertSubscriptionsPageMailStatusMessage();

    $mails = $this->getMails();
    $this->assertCount(1, $mails);
    $this->assertMailProperty('to', $email);
    $site_url = Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString();
    $this->assertMailProperty('subject', "Access your subscriptions page on $site_url");
    $this->assertMailString('body', "You are receiving this e-mail because you requested access to your subscriptions page on $site_url.");
    $this->assertMailString('body', 'Click the following link to access your subscriptions page: Access my subscriptions page [1]');
    $this->assertMailString('body', "If you didn't request access to your subscriptions page or you're not sure why you received this e-mail, you can delete it.");
    $mail_urls = $this->getMailFootNoteUrls($mails[0]['body']);
    $this->assertCount(1, $mail_urls);
    $base_path = $this->getAbsoluteUrl('/user/subscriptions/' . rawurlencode($email));
    $this->assertMatchesRegularExpression('@^' . preg_quote($base_path, '@') . '/.+$@', $mail_urls[1]);
    $this->resetMailCollector();

    return $mail_urls[1];
  }

}
