<?php if ( ! defined( 'ABSPATH' ) ) exit;
/** @var SMEC_Settings  $settings */
/** @var SMEC_Notifier  $notifier */

$cfg   = $settings->get_notification_settings();
$state = $notifier->get_state_public();

$event_labels = [
    SMEC_Notifier::EVENT_API_STREAK       => 'Opakujici se API chyby (x za sebou)',
    SMEC_Notifier::EVENT_IMPORT_FAILURES  => 'Selhani importu ve fronte',
    SMEC_Notifier::EVENT_CRON_STOPPED     => 'WP-Cron se zastavil',
    SMEC_Notifier::EVENT_IMPORT_SILENCE   => 'Zadne importy po delsi dobu',
    SMEC_Notifier::EVENT_WEBTRACK_SILENCE => 'Webtracking se nevkladal (experimentalni)',
];

$threshold_labels = [
    SMEC_Notifier::EVENT_API_STREAK       => [ 'suffix' => 'chyb za sebou', 'min' => 1, 'max' => 20 ],
    SMEC_Notifier::EVENT_IMPORT_FAILURES  => [ 'suffix' => 'polozek ve fronte', 'min' => 1, 'max' => 100 ],
    SMEC_Notifier::EVENT_CRON_STOPPED     => [ 'suffix' => 'hodin bez behu', 'min' => 1, 'max' => 72 ],
    SMEC_Notifier::EVENT_IMPORT_SILENCE   => [ 'suffix' => 'hodin bez importu', 'min' => 1, 'max' => 168 ],
    SMEC_Notifier::EVENT_WEBTRACK_SILENCE => [ 'suffix' => 'hodin bez vkladani', 'min' => 1, 'max' => 168 ],
];
?>
<div class="wrap smec-wrap">
<h1>Notifikace</h1>

<div class="smec-card smec-help-card smec-help-compact">
  <p>
    <strong>Jak to funguje:</strong> Plugin kontroluje stav jednou za hodinu (WP-Cron).
    Pokud detekuje vypadek, posle notifikaci nakonfigurovanymi kanaly.
    Kazdy typ upozorneni ma <strong>cooldown</strong> – stejny problem nebude posilat zpravy opakovane.
  </p>
  <p class="smec-muted">
    Notifikace se neposleji pri prvotnim nastaveni pluginu – pouze kdyz funkce driv fungovala a nyni doslo ke zmene.
  </p>
</div>

<div class="smec-two-col">

<!-- ── Aktivace a kanaly ─────────────────────────────── -->
<div class="smec-card">
  <h2>Notifikacni kanaly</h2>

  <table class="form-table smec-form-table">
    <tr>
      <th><label for="notif-enabled">Aktivovat notifikace</label></th>
      <td>
        <label class="smec-toggle">
          <input type="checkbox" id="notif-enabled" value="1" <?php checked( ! empty( $cfg['enabled'] ) ); ?>>
          <span class="smec-toggle-slider"></span>
        </label>
      </td>
    </tr>
    <tr>
      <th><label for="notif-email">E-mail adresy</label></th>
      <td>
        <textarea id="notif-email" rows="3" class="large-text"
          placeholder="admin@example.com&#10;druhy@example.com"
        ><?php echo esc_textarea( implode( "\n", (array) ( $cfg['email'] ?? [] ) ) ); ?></textarea>
        <p class="description">Kazda adresa na samostatnem radku, nebo oddelene carkou.</p>
      </td>
    </tr>
    <tr>
      <th><label for="notif-slack">Slack Webhook URL</label></th>
      <td>
        <input type="url" id="notif-slack" class="large-text"
          value="<?php echo esc_attr( $cfg['slack_webhook'] ?? '' ); ?>"
          placeholder="https://hooks.slack.com/services/T.../B.../...">
        <p class="description">
          Jak ziskat: Slack → Apps → Incoming Webhooks → Add to Slack.
        </p>
      </td>
    </tr>
    <tr>
      <th><label for="notif-webhook">Generic Webhook URL</label></th>
      <td>
        <input type="url" id="notif-webhook" class="large-text"
          value="<?php echo esc_attr( $cfg['webhook_url'] ?? '' ); ?>"
          placeholder="https://vas-server.cz/webhook">
        <p class="description">POST pozadavek s JSON payloadem (event, subject, message, site_url, timestamp).</p>
      </td>
    </tr>
    <tr>
      <th><label for="notif-cooldown">Cooldown (hodiny)</label></th>
      <td>
        <input type="number" id="notif-cooldown" class="small-text"
          min="1" max="168"
          value="<?php echo (int) ( $cfg['cooldown_hours'] ?? 24 ); ?>">
        <p class="description">
          Stejny typ upozorneni se nebude posilat casteje nez jednou za zadany pocet hodin.
          Doporuceno: 24 h.
        </p>
      </td>
    </tr>
  </table>

  <?php
  $mail_diag = $notifier->get_mail_diagnostics();
  if ( $mail_diag['is_localhost'] && ! $mail_diag['smtp_plugin'] ) : ?>
    <div class="smec-help-note" style="margin-bottom:16px;">
      <strong>⚠️ Localhost bez SMTP:</strong> Bezite na localhostu (<code><?php echo esc_html( $_SERVER['SERVER_NAME'] ?? '' ); ?></code>).
      WordPress nemuze odesilat e-maily bez SMTP pluginu.
      Nainstalujte <strong>WP Mail SMTP</strong> nebo <strong>FluentSMTP</strong> a nakonfigurujte SMTP server (napr. Gmail, Mailtrap, Brevo).
      Pro testovani na localhostu doporucujeme <a href="https://mailtrap.io" target="_blank" rel="noopener">Mailtrap</a> — zachytava e-maily a nezpoplatnuje odeslani.
    </div>
  <?php elseif ( ! $mail_diag['smtp_plugin'] ) : ?>
    <div class="smec-help-note" style="margin-bottom:16px;">
      <strong>ℹ️ SMTP plugin neni aktivni.</strong>
      Pokud e-maily neprichazeji, zvazujte instalaci <strong>WP Mail SMTP</strong> pro spolehlivejsi dorucovani.
    </div>
  <?php else : ?>
    <div style="color:#007017;margin-bottom:12px;font-size:0.875rem;">
      ✓ SMTP plugin aktivni: <strong><?php echo esc_html( $mail_diag['smtp_plugin'] ); ?></strong>
    </div>
  <?php endif; ?>

  <div class="smec-actions">
    <button type="button" id="smec-save-notifications" class="button button-primary">Ulozit nastaveni</button>
    <button type="button" id="smec-test-notification" class="button">Odeslat testovaci zpravu</button>
    <span id="smec-notif-result" class="smec-inline-result"></span>
  </div>
  <div id="smec-test-result-wrap"></div>
</div>

<!-- ── Udalosti ──────────────────────────────────────── -->
<div class="smec-card">
  <h2>Udalosti a prahy</h2>
  <p class="smec-muted">Vyberte, ktere problemy chcete sledovat, a nastavte prah pro spusteni upozorneni.</p>

  <table class="wp-list-table widefat smec-notif-events-table">
    <thead>
      <tr>
        <th style="width:40px;">Aktivni</th>
        <th>Udalost</th>
        <th style="width:120px;">Prah</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ( $event_labels as $event => $label ) :
        $enabled   = ! empty( $cfg['events'][ $event ] );
        $threshold = (int) ( $cfg['thresholds'][ $event ] ?? 3 );
        $tl        = $threshold_labels[ $event ];
      ?>
        <tr>
          <td>
            <input type="checkbox" class="notif-event-checkbox"
              data-event="<?php echo esc_attr( $event ); ?>"
              value="1" <?php checked( $enabled ); ?>>
          </td>
          <td><?php echo esc_html( $label ); ?></td>
          <td>
            <input type="number" class="notif-threshold-input small-text"
              data-event="<?php echo esc_attr( $event ); ?>"
              min="<?php echo (int) $tl['min']; ?>"
              max="<?php echo (int) $tl['max']; ?>"
              value="<?php echo (int) $threshold; ?>">
            <span class="smec-muted" style="font-size:0.75rem;"><?php echo esc_html( $tl['suffix'] ); ?></span>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

</div><!-- /two-col -->

<!-- ── Stav notifikatoru ─────────────────────────────── -->
<div class="smec-card">
  <div class="smec-card-header">
    <h2>Aktualní stav</h2>
    <button type="button" id="smec-reset-notif-state" class="button button-small">Vynulovat stav</button>
  </div>
  <p class="smec-muted">Informace o tom, co plugin naposledy detekoval a kdy byla odeslana posledni upozorneni.</p>

  <table class="widefat striped">
    <tr>
      <td>Posledni kontrola</td>
      <td><?php echo ! empty( $state['last_check'] ) ? esc_html( date_i18n( 'j.n.Y H:i', $state['last_check'] ) ) : 'Zatim neprobehla'; ?></td>
    </tr>
    <tr>
      <td>Posledni beh cronu (fronta)</td>
      <td><?php echo ! empty( $state['cron_last_run'] ) ? esc_html( date_i18n( 'j.n.Y H:i', $state['cron_last_run'] ) ) : '—'; ?></td>
    </tr>
    <tr>
      <td>Aktualni API chyby za sebou</td>
      <td><?php echo (int) ( $state['api_error_count'] ?? 0 ); ?></td>
    </tr>
    <tr>
      <td>Posledni injekce webtrackingu</td>
      <td><?php echo ! empty( $state['webtrack_last_inject'] ) ? esc_html( date_i18n( 'j.n.Y H:i', $state['webtrack_last_inject'] ) ) : '—'; ?></td>
    </tr>
    <?php
    $events_sent = $state['last_sent'] ?? [];
    $event_names = [
        SMEC_Notifier::EVENT_API_STREAK       => 'API chyby',
        SMEC_Notifier::EVENT_IMPORT_FAILURES  => 'Selhane importy',
        SMEC_Notifier::EVENT_CRON_STOPPED     => 'Cron zastaven',
        SMEC_Notifier::EVENT_IMPORT_SILENCE   => 'Zadne importy',
        SMEC_Notifier::EVENT_WEBTRACK_SILENCE => 'Webtracking ticho',
    ];
    foreach ( $event_names as $key => $label ) :
      $ts = $events_sent[ $key ] ?? 0;
    ?>
      <tr>
        <td>Posledni odeslani: <?php echo esc_html( $label ); ?></td>
        <td><?php echo $ts ? esc_html( date_i18n( 'j.n.Y H:i', $ts ) ) : 'Nikdy'; ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <p class="smec-muted" style="margin-top:8px;font-size:0.8rem;">
    Vynulovanim stavu resetujete cooldown – nasledujici kontrola muze znovu odeslat upozorneni, pokud bude splnen prah.
  </p>
</div>

</div>
