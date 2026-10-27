{**
 * Claude Connector by Piskee
 *
 * @author    Piskee <louisalexandre@piskee.com>
 * @copyright 2026 Piskee
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License v3.0 (AFL-3.0)
 *}
{assign var=t value=$pkc.t}
<div class="pkc pkc-consent">
  <header class="pkc-hero">
    <div class="pkc-hero__brand">
      <img class="pkc-hero__logo" src="{$pkc.logo|escape:'html':'UTF-8'}" alt="Piskee" width="56" height="56">
      <div>
        <h1 class="pkc-hero__title">{$t.title|escape:'html':'UTF-8'}</h1>
        <p class="pkc-hero__by">Piskee for PrestaShop</p>
      </div>
    </div>
  </header>

  {if $pkc.expired}
    <div class="pkc-card">
      <h2>{$t.expired_title|escape:'html':'UTF-8'}</h2>
      <p>{$t.expired_text|escape:'html':'UTF-8'}</p>
    </div>
  {else}
    <div class="pkc-card">
      <p class="pkc-consent__lead"><strong>{$pkc.client|escape:'html':'UTF-8'}</strong> {$t.lead|escape:'html':'UTF-8'}</p>
      <table class="pkc-table">
        <tbody>
          <tr><th>{$t.application|escape:'html':'UTF-8'}</th><td><strong>{$pkc.client|escape:'html':'UTF-8'}</strong></td></tr>
          <tr><th>{$t.returns_to|escape:'html':'UTF-8'}</th><td><code>{$pkc.redirect_host|escape:'html':'UTF-8'}</code></td></tr>
          <tr><th>{$t.as_employee|escape:'html':'UTF-8'}</th><td>{$pkc.employee|escape:'html':'UTF-8'} · {$pkc.profile|escape:'html':'UTF-8'}</td></tr>
          <tr><th>{$t.shop|escape:'html':'UTF-8'}</th><td>{$pkc.shop|escape:'html':'UTF-8'}</td></tr>
        </tbody>
      </table>

      <h3>{$t.rights|escape:'html':'UTF-8'}</h3>
      <ul class="pkc-checks">
        {foreach $pkc.scopes as $scope}
          <li class="{if $scope.writes}is-ko{else}is-ok{/if}">
            <span class="pkc-checks__icon" aria-hidden="true">{if $scope.writes}&#9998;{else}&#10003;{/if}</span>
            <span class="pkc-checks__label">{$scope.label|escape:'html':'UTF-8'}</span>
          </li>
        {/foreach}
      </ul>
      <p class="pkc-muted">{$t.limits|escape:'html':'UTF-8'}</p>

      <form method="post" action="{$pkc.action|escape:'html':'UTF-8'}" class="pkc-buttons">
        <input type="hidden" name="nonce" value="{$pkc.nonce|escape:'html':'UTF-8'}">
        <input type="hidden" name="request" value="{$pkc.request|escape:'html':'UTF-8'}">
        <button type="submit" name="piskee_decision" value="allow" class="pkc-btn">{$t.allow|escape:'html':'UTF-8'}</button>
        <button type="submit" name="piskee_decision" value="deny" class="pkc-btn pkc-btn--ghost">{$t.deny|escape:'html':'UTF-8'}</button>
      </form>
    </div>
  {/if}

  <p class="pkc-muted pkc-consent__help">{$t.help|escape:'html':'UTF-8'} <a href="{$pkc.contact|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{$pkc.email|escape:'html':'UTF-8'}</a></p>
</div>
