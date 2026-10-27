{**
 * Claude Connector by Piskee
 *
 * @author    Piskee <louisalexandre@piskee.com>
 * @copyright 2026 Piskee
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License v3.0 (AFL-3.0)
 *}
{assign var=t value=$pk.t}
<div class="pkc" data-copied="{$t.copied|escape:'html':'UTF-8'}">

  <header class="pkc-hero">
    <div class="pkc-hero__brand">
      <img class="pkc-hero__logo" src="{$pk.logo|escape:'html':'UTF-8'}" alt="Piskee" width="56" height="56">
      <div>
        <h1 class="pkc-hero__title">{$t.title|escape:'html':'UTF-8'} <span class="pkc-badge pkc-badge--light">v{$pk.version|escape:'html':'UTF-8'}</span></h1>
        <p class="pkc-hero__by">{$t.by|escape:'html':'UTF-8'}</p>
        <p class="pkc-made"><span class="pkc-made__flag" aria-hidden="true"><i style="background:#0055A4"></i><i style="background:#fff"></i><i style="background:#EF4135"></i></span>{$t.made_in|escape:'html':'UTF-8'}</p>
      </div>
    </div>
    <p class="pkc-hero__tagline">{$t.tagline|escape:'html':'UTF-8'}</p>
    <div class="pkc-hero__actions">
      <span class="pkc-status {if $pk.ready}pkc-status--ok{else}pkc-status--todo{/if}">
        <span class="pkc-status__dot"></span>{if $pk.ready}{$t.status_ok|escape:'html':'UTF-8'}{else}{$t.status_todo|escape:'html':'UTF-8'}{/if}
      </span>
      <a class="pkc-btn pkc-btn--cream" href="{$pk.brand.contact|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{$t.help_cta|escape:'html':'UTF-8'}</a>
      <a class="pkc-link-light" href="{$pk.switch_url|escape:'html':'UTF-8'}">{$t.lang_switch|escape:'html':'UTF-8'}</a>
    </div>
  </header>

  {if $pk.notice}
    <div class="pkc-notice pkc-notice--{$pk.notice_type|escape:'html':'UTF-8'}">{$pk.notice|escape:'html':'UTF-8'}</div>
  {/if}

  <nav class="pkc-tabs" role="tablist">
    <button type="button" class="pkc-tab is-active" data-tab="connect">{$t.tab_connect|escape:'html':'UTF-8'}</button>
    <button type="button" class="pkc-tab" data-tab="tools">{$t.tab_tools|escape:'html':'UTF-8'} <span class="pkc-count">{$pk.tool_count|intval}</span></button>
    <button type="button" class="pkc-tab" data-tab="theme">{$t.tab_theme|escape:'html':'UTF-8'}</button>
    <button type="button" class="pkc-tab" data-tab="docs">{$t.tab_docs|escape:'html':'UTF-8'}</button>
    <button type="button" class="pkc-tab" data-tab="pro">{$t.tab_pro|escape:'html':'UTF-8'}</button>
    <button type="button" class="pkc-tab" data-tab="about">{$t.tab_about|escape:'html':'UTF-8'}</button>
  </nav>

  <div class="pkc-layout">
    <main class="pkc-main">

      {* ---------- Connection ---------- *}
      <section class="pkc-panel is-active" data-panel="connect">
        <div class="pkc-card pkc-welcome">
          <h2>{$t.welcome_title|escape:'html':'UTF-8'}</h2>
          <ul class="pkc-list-check">
            <li>{$t.w1|escape:'html':'UTF-8'}</li>
            <li>{$t.w2|escape:'html':'UTF-8'}</li>
            <li>{$t.w3|escape:'html':'UTF-8'}</li>
            <li>{$t.w4|escape:'html':'UTF-8'}</li>
            <li>{$t.w5|escape:'html':'UTF-8'}</li>
            <li>{$t.w6|escape:'html':'UTF-8'}</li>
            <li>{$t.w7|escape:'html':'UTF-8'}</li>
          </ul>
        </div>

        <div class="pkc-card">
          <h2>{$t.status|escape:'html':'UTF-8'}</h2>
          <ul class="pkc-checks">
            {foreach $pk.checks as $check}
              <li class="{if $check.ok}is-ok{else}is-ko{/if}">
                <span class="pkc-checks__icon" aria-hidden="true">{if $check.ok}&#10003;{else}!{/if}</span>
                {$check.label|escape:'html':'UTF-8'}
              </li>
            {/foreach}
            {if $pk.test}
              {foreach $pk.test as $probe}
                <li class="{if $probe.ok}is-ok{else}is-ko{/if}">
                  <span class="pkc-checks__icon" aria-hidden="true">{if $probe.ok}&#10003;{else}!{/if}</span>
                  {$probe.label|escape:'html':'UTF-8'} <small class="pkc-muted">{$probe.detail|escape:'html':'UTF-8'}</small>
                </li>
              {/foreach}
            {/if}
          </ul>
          <div class="pkc-buttons">
            <form method="post" action="{$pk.action_url|escape:'html':'UTF-8'}" class="pkc-inline-form">
              <input type="hidden" name="piskee_token" value="{$pk.token|escape:'html':'UTF-8'}">
              <input type="hidden" name="piskee_action" value="run_test">
              <button type="submit" class="pkc-btn">{$t.run_test|escape:'html':'UTF-8'}</button>
            </form>
            <form method="post" action="{$pk.action_url|escape:'html':'UTF-8'}" class="pkc-inline-form">
              <input type="hidden" name="piskee_token" value="{$pk.token|escape:'html':'UTF-8'}">
              <input type="hidden" name="piskee_action" value="toggle_server">
              <button type="submit" class="pkc-btn pkc-btn--ghost">{if $pk.enabled}{$t.server_toggle_on|escape:'html':'UTF-8'}{else}{$t.server_toggle_off|escape:'html':'UTF-8'}{/if}</button>
            </form>
          </div>
        </div>

        <div class="pkc-card">
          <h2>{$t.address|escape:'html':'UTF-8'}</h2>
          <div class="pkc-copy">
            <code class="pkc-copy__value" id="pkc-mcp-url">{$pk.mcp_url|escape:'html':'UTF-8'}</code>
            <button type="button" class="pkc-btn" data-copy="#pkc-mcp-url">{$t.copy|escape:'html':'UTF-8'}</button>
          </div>
        </div>

        <div class="pkc-card">
          <h2>{$t.steps|escape:'html':'UTF-8'}</h2>
          <ol class="pkc-steps">
            <li>{$t.step1|escape:'html':'UTF-8'}</li>
            <li>{$t.step2|escape:'html':'UTF-8'}</li>
            <li>{$t.step3|escape:'html':'UTF-8'}</li>
            <li>{$t.step4|escape:'html':'UTF-8'}</li>
            <li>{$t.step5|escape:'html':'UTF-8'}</li>
          </ol>
          <h3>{$t.claude_code|escape:'html':'UTF-8'}</h3>
          <div class="pkc-copy">
            <code class="pkc-copy__value" id="pkc-cc">claude mcp add --transport http piskee {$pk.mcp_url|escape:'html':'UTF-8'}</code>
            <button type="button" class="pkc-btn pkc-btn--ghost" data-copy="#pkc-cc">{$t.copy|escape:'html':'UTF-8'}</button>
          </div>
          <h3>{$t.try|escape:'html':'UTF-8'}</h3>
          <ul class="pkc-prompts">
            <li>{$t.try1|escape:'html':'UTF-8'}</li>
            <li>{$t.try2|escape:'html':'UTF-8'}</li>
            <li>{$t.try3|escape:'html':'UTF-8'}</li>
          </ul>
        </div>

        <div class="pkc-card">
          <h2>{$t.reco_title|escape:'html':'UTF-8'}</h2>
          <ul class="pkc-list-check">
            <li>{$t.reco1|escape:'html':'UTF-8'}</li>
            <li>{$t.reco2|escape:'html':'UTF-8'}</li>
            <li>{$t.reco3|escape:'html':'UTF-8'}</li>
            <li>{$t.reco4|escape:'html':'UTF-8'}</li>
          </ul>
        </div>

        <div class="pkc-card">
          <h2>{$t.connections|escape:'html':'UTF-8'}</h2>
          {if $pk.connections}
            <table class="pkc-table">
              <thead><tr><th>{$t.conn_app|escape:'html':'UTF-8'}</th><th>{$t.conn_employee|escape:'html':'UTF-8'}</th><th>{$t.conn_last|escape:'html':'UTF-8'}</th><th></th></tr></thead>
              <tbody>
                {foreach $pk.connections as $conn}
                  <tr>
                    <td><strong>{$conn.client|escape:'html':'UTF-8'}</strong><br><small>{$conn.shop|escape:'html':'UTF-8'}</small></td>
                    <td>{$conn.employee|escape:'html':'UTF-8'}</td>
                    <td>{if $conn.last_used}{$conn.last_used|escape:'html':'UTF-8'}{else}{$conn.since|escape:'html':'UTF-8'}{/if}</td>
                    <td class="pkc-right">
                      <form method="post" action="{$pk.action_url|escape:'html':'UTF-8'}" class="pkc-inline-form">
                        <input type="hidden" name="piskee_token" value="{$pk.token|escape:'html':'UTF-8'}">
                        <input type="hidden" name="piskee_action" value="revoke">
                        <input type="hidden" name="family" value="{$conn.family|escape:'html':'UTF-8'}">
                        <button type="submit" class="pkc-btn pkc-btn--small pkc-btn--ghost">{$t.conn_revoke|escape:'html':'UTF-8'}</button>
                      </form>
                    </td>
                  </tr>
                {/foreach}
              </tbody>
            </table>
          {else}
            <p class="pkc-empty">{$t.connections_none|escape:'html':'UTF-8'}</p>
          {/if}
        </div>
      </section>

      {* ---------- What it does ---------- *}
      <section class="pkc-panel" data-panel="tools">
        <div class="pkc-card">
          <h2>{$t.does|escape:'html':'UTF-8'}</h2>
          <div class="pkc-groups">
            {foreach $pk.groups as $group}
              <div class="pkc-group">
                <h3>{$group.label|escape:'html':'UTF-8'}</h3>
                <ul>
                  {foreach $group.tools as $tool}
                    <li>
                      <span>{$tool.title|escape:'html':'UTF-8'}</span>
                      <span class="pkc-badge {if $tool.writes}pkc-badge--write{else}pkc-badge--read{/if}">{if $tool.writes}{$t.writes|escape:'html':'UTF-8'}{else}{$t.reads|escape:'html':'UTF-8'}{/if}</span>
                    </li>
                  {/foreach}
                </ul>
              </div>
            {/foreach}
          </div>
        </div>
        <div class="pkc-two">
          <div class="pkc-card pkc-card--warn">
            <h2>{$t.does_not|escape:'html':'UTF-8'}</h2>
            <ul class="pkc-list-x">
              <li>{$t.not1|escape:'html':'UTF-8'}</li>
              <li>{$t.not2|escape:'html':'UTF-8'}</li>
              <li>{$t.not3|escape:'html':'UTF-8'}</li>
              <li>{$t.not4|escape:'html':'UTF-8'}</li>
              <li>{$t.not5|escape:'html':'UTF-8'}</li>
              <li>{$t.not6|escape:'html':'UTF-8'}</li>
            </ul>
          </div>
          <div class="pkc-card">
            <h2>{$t.safety|escape:'html':'UTF-8'}</h2>
            <ul class="pkc-list-check">
              <li>{$t.safety1|escape:'html':'UTF-8'}</li>
              <li>{$t.safety2|escape:'html':'UTF-8'}</li>
              <li>{$t.safety3|escape:'html':'UTF-8'}</li>
            </ul>
          </div>
        </div>
      </section>

      {* ---------- Theme ---------- *}
      <section class="pkc-panel" data-panel="theme">
        <div class="pkc-card">
          <h2>{$t.theme_title|escape:'html':'UTF-8'}</h2>
          <p class="pkc-muted">{$t.theme_intro|escape:'html':'UTF-8'}</p>
          <table class="pkc-table">
            <thead><tr><th>{$t.theme_name|escape:'html':'UTF-8'}</th><th>{$t.theme_parent|escape:'html':'UTF-8'}</th><th>{$t.theme_state|escape:'html':'UTF-8'}</th><th></th></tr></thead>
            <tbody>
              {foreach $pk.themes as $theme}
                <tr>
                  <td><strong>{$theme.display_name|escape:'html':'UTF-8'}</strong><br><small>{$theme.name|escape:'html':'UTF-8'}</small></td>
                  <td>{if $theme.parent}{$theme.parent|escape:'html':'UTF-8'}{else}<span class="pkc-muted">{$t.theme_parent_theme|escape:'html':'UTF-8'}</span>{/if}</td>
                  <td>
                    {if $theme.live_here}<span class="pkc-badge pkc-badge--live">{$t.theme_live|escape:'html':'UTF-8'}</span>
                    {elseif $theme.is_child}<span class="pkc-badge pkc-badge--draft">{$t.theme_draft|escape:'html':'UTF-8'}</span>
                    {else}<span class="pkc-badge">{$t.theme_parent_theme|escape:'html':'UTF-8'}</span>{/if}
                  </td>
                  <td class="pkc-right">
                    {if $theme.is_child && !$theme.live_here}
                      <form method="post" action="{$pk.action_url|escape:'html':'UTF-8'}" class="pkc-inline-form" data-confirm="{$t.theme_confirm|escape:'html':'UTF-8'}">
                        <input type="hidden" name="piskee_token" value="{$pk.token|escape:'html':'UTF-8'}">
                        <input type="hidden" name="piskee_action" value="activate_theme">
                        <input type="hidden" name="theme" value="{$theme.name|escape:'html':'UTF-8'}">
                        <button type="submit" class="pkc-btn pkc-btn--small">{$t.theme_activate|escape:'html':'UTF-8'}</button>
                      </form>
                    {/if}
                  </td>
                </tr>
              {/foreach}
            </tbody>
          </table>
          {assign var=hasChild value=false}
          {foreach $pk.themes as $theme}{if $theme.is_child}{assign var=hasChild value=true}{/if}{/foreach}
          {if !$hasChild}<p class="pkc-empty">{$t.theme_none|escape:'html':'UTF-8'}</p>{/if}
          {if $pk.previous_theme}
            <form method="post" action="{$pk.action_url|escape:'html':'UTF-8'}" class="pkc-rollback">
              <input type="hidden" name="piskee_token" value="{$pk.token|escape:'html':'UTF-8'}">
              <input type="hidden" name="piskee_action" value="rollback_theme">
              <button type="submit" class="pkc-btn pkc-btn--ghost">{$t.theme_rollback|escape:'html':'UTF-8'} ({$pk.previous_theme|escape:'html':'UTF-8'})</button>
            </form>
          {/if}
        </div>
      </section>

      {* ---------- Docs ---------- *}
      <section class="pkc-panel" data-panel="docs">
        <div class="pkc-card pkc-doc">
          <p class="pkc-muted">{$t.docs_intro|escape:'html':'UTF-8'}</p>
          <div class="pkc-doc__switch">
            <button type="button" class="pkc-chip {if $pk.lang == 'fr'}is-active{/if}" data-doc="fr">Français</button>
            <button type="button" class="pkc-chip {if $pk.lang != 'fr'}is-active{/if}" data-doc="en">English</button>
          </div>
          <article class="pkc-doc__body {if $pk.lang == 'fr'}is-active{/if}" data-doc-body="fr">{$pk.docs.fr nofilter}</article>
          <article class="pkc-doc__body {if $pk.lang != 'fr'}is-active{/if}" data-doc-body="en">{$pk.docs.en nofilter}</article>
        </div>
      </section>

      {* ---------- Free / Pro ---------- *}
      <section class="pkc-panel" data-panel="pro">
        <div class="pkc-card">
          <h2>{$t.plan_current|escape:'html':'UTF-8'} : {if $pk.plan.license.valid}{$t.plan_pro|escape:'html':'UTF-8'}{else}{$t.plan_free|escape:'html':'UTF-8'}{/if}</h2>
          {if $pk.plan.license.valid}
            <p>{$t.plan_valid|escape:'html':'UTF-8'} <strong>{$pk.plan.license.expires|escape:'html':'UTF-8'}</strong> {$t.plan_for|escape:'html':'UTF-8'} <strong>{$pk.plan.license.domain|escape:'html':'UTF-8'}</strong>.</p>
          {else}
            <div class="pkc-usage">
              <div class="pkc-usage__bar"><div class="pkc-usage__fill {if $pk.plan.percent >= 80}is-high{/if}" style="width: {$pk.plan.percent|intval}%"></div></div>
              <div class="pkc-usage__label"><span><strong>{$pk.plan.used|intval} / {$pk.plan.allowance|intval}</strong> {$t.plan_usage|escape:'html':'UTF-8'}</span></div>
            </div>
            <p class="pkc-muted">{$t.plan_reads|escape:'html':'UTF-8'}</p>
          {/if}
          <h3>{$t.license_title|escape:'html':'UTF-8'}</h3>
          <form method="post" action="{$pk.action_url|escape:'html':'UTF-8'}" class="pkc-license">
            <input type="hidden" name="piskee_token" value="{$pk.token|escape:'html':'UTF-8'}">
            <input type="hidden" name="piskee_action" value="save_license">
            <input type="text" name="license_key" placeholder="{$t.license_placeholder|escape:'html':'UTF-8'}" autocomplete="off" spellcheck="false">
            <button type="submit" class="pkc-btn">{$t.license_save|escape:'html':'UTF-8'}</button>
          </form>
        </div>

        <div class="pkc-card">
          <h2>{$t.pro_title|escape:'html':'UTF-8'}</h2>
          <p class="pkc-muted">{$t.pro_intro|escape:'html':'UTF-8'}</p>
          <table class="pkc-table pkc-compare">
            <thead><tr><th>{$t.compare_feature|escape:'html':'UTF-8'}</th><th>{$t.plan_free|escape:'html':'UTF-8'}</th><th>{$t.plan_pro|escape:'html':'UTF-8'}</th></tr></thead>
            <tbody>
              <tr><td>{$t.row_tools|escape:'html':'UTF-8'}</td><td class="yes">&#10003;</td><td class="yes">&#10003;</td></tr>
              <tr><td>{$t.row_writes|escape:'html':'UTF-8'}</td><td>{$t.row_writes_free|escape:'html':'UTF-8'}</td><td class="yes">{$t.row_writes_pro|escape:'html':'UTF-8'}</td></tr>
              <tr><td>{$t.row_bulk|escape:'html':'UTF-8'}</td><td>{$t.within_quota|escape:'html':'UTF-8'}</td><td class="yes">&#10003;</td></tr>
              <tr><td>{$t.row_redirects|escape:'html':'UTF-8'}</td><td class="no">&#8212;</td><td class="yes">&#10003;</td></tr>
              <tr><td>{$t.row_refunds|escape:'html':'UTF-8'}</td><td class="no">&#8212;</td><td class="yes">&#10003;</td></tr>
              <tr><td>{$t.row_multistore|escape:'html':'UTF-8'}</td><td class="no">&#8212;</td><td class="yes">&#10003;</td></tr>
              <tr><td>{$t.row_support|escape:'html':'UTF-8'}</td><td class="no">&#8212;</td><td class="yes">&#10003;</td></tr>
            </tbody>
          </table>
        </div>

        <div class="pkc-pricing">
          <div class="pkc-card pkc-card--pro">
            <p class="pkc-audit__kicker">{$t.plan_pro|escape:'html':'UTF-8'}</p>
            <p class="pkc-plan"><span class="pkc-plan__price">{$pk.plan.price|escape:'html':'UTF-8'}</span></p>
            <p class="pkc-muted">{$t.pro_note|escape:'html':'UTF-8'}</p>
            <a class="pkc-btn pkc-btn--block" href="{$pk.brand.contact_pro|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{$t.pro_cta|escape:'html':'UTF-8'}</a>
          </div>
          <div class="pkc-card">
            <p class="pkc-audit__kicker">{$t.onboarding_title|escape:'html':'UTF-8'}</p>
            <p class="pkc-plan"><span class="pkc-plan__price">{$pk.plan.onboarding|escape:'html':'UTF-8'}</span></p>
            <p class="pkc-muted">{$t.onboarding_text|escape:'html':'UTF-8'}</p>
            <a class="pkc-btn pkc-btn--ghost pkc-btn--block" href="{$pk.brand.contact_pro|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{$t.onboarding_cta|escape:'html':'UTF-8'}</a>
          </div>
        </div>
      </section>

      {* ---------- About ---------- *}
      <section class="pkc-panel" data-panel="about">
        <div class="pkc-card pkc-story">
          <img src="{$pk.logo_olive|escape:'html':'UTF-8'}" alt="Piskee" width="96">
          <div>
            <h2>{$t.story_title|escape:'html':'UTF-8'}</h2>
            <p>{$t.story_text|escape:'html':'UTF-8'}</p>
          </div>
        </div>
        <div class="pkc-card">
          <h2>{$t.about_title|escape:'html':'UTF-8'}</h2>
          <p>{$t.about_text|escape:'html':'UTF-8'}</p>
          <p class="pkc-services">{$t.about_services|escape:'html':'UTF-8'}</p>
          <div class="pkc-buttons">
            <a class="pkc-btn" href="{$pk.brand.website|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{$t.website|escape:'html':'UTF-8'}</a>
            <a class="pkc-btn pkc-btn--ghost" href="{$pk.brand.contact|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{$t.audit_cta|escape:'html':'UTF-8'}</a>
            <a class="pkc-btn pkc-btn--ghost" href="{$pk.brand.linkedin|escape:'html':'UTF-8'}" target="_blank" rel="noopener">LinkedIn</a>
          </div>
          <hr>
          <p class="pkc-muted">{$t.about_open|escape:'html':'UTF-8'}</p>
          <p><a href="{$pk.brand.repo|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{$t.about_repo|escape:'html':'UTF-8'}</a></p>
        </div>
        <div class="pkc-card pkc-legal">
          <h2>{$t.legal_title|escape:'html':'UTF-8'}</h2>
          <p>{$t.legal1|escape:'html':'UTF-8'}</p>
          <p>{$t.legal2|escape:'html':'UTF-8'}</p>
          <p>{$t.legal3|escape:'html':'UTF-8'}</p>
          <p>{$t.legal4|escape:'html':'UTF-8'}</p>
        </div>
      </section>
    </main>

    <aside class="pkc-aside">
      <div class="pkc-card pkc-plan-card">
        <h2>{$t.plan_current|escape:'html':'UTF-8'} : {if $pk.plan.license.valid}{$t.plan_pro|escape:'html':'UTF-8'}{else}{$t.plan_free|escape:'html':'UTF-8'}{/if}</h2>
        {if $pk.plan.license.valid}
          <p class="pkc-muted">{$t.plan_valid|escape:'html':'UTF-8'} <strong>{$pk.plan.license.expires|escape:'html':'UTF-8'}</strong>.</p>
        {else}
          <div class="pkc-usage">
            <div class="pkc-usage__bar"><div class="pkc-usage__fill {if $pk.plan.percent >= 80}is-high{/if}" style="width: {$pk.plan.percent|intval}%"></div></div>
            <div class="pkc-usage__label"><span><strong>{$pk.plan.used|intval} / {$pk.plan.allowance|intval}</strong> {$t.plan_usage|escape:'html':'UTF-8'}</span></div>
          </div>
          <p class="pkc-muted">{$t.plan_reads|escape:'html':'UTF-8'}</p>
          <button type="button" class="pkc-btn pkc-btn--block" data-open-tab="pro">{$t.pro_cta|escape:'html':'UTF-8'}</button>
        {/if}
      </div>
      <div class="pkc-card pkc-help">
        <h2>{$t.help_title|escape:'html':'UTF-8'}</h2>
        <p>{$t.help_text|escape:'html':'UTF-8'}</p>
        <a class="pkc-btn pkc-btn--block" href="{$pk.brand.contact|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{$t.help_cta|escape:'html':'UTF-8'}</a>
        <a class="pkc-btn pkc-btn--ghost pkc-btn--block" href="{$pk.brand.whatsapp|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{$t.help_whatsapp|escape:'html':'UTF-8'} · {$pk.brand.phone|escape:'html':'UTF-8'}</a>
        <a class="pkc-btn pkc-btn--ghost pkc-btn--block" href="mailto:{$pk.brand.email|escape:'html':'UTF-8'}?subject=Claude%20Connector">{$pk.brand.email|escape:'html':'UTF-8'}</a>
      </div>
      <div class="pkc-card pkc-audit">
        <p class="pkc-audit__kicker">Piskee</p>
        <p class="pkc-audit__title">{$t.audit_cta|escape:'html':'UTF-8'}</p>
        <a class="pkc-btn pkc-btn--cream pkc-btn--block" href="{$pk.brand.contact|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{$t.help_cta|escape:'html':'UTF-8'}</a>
      </div>
    </aside>
  </div>

  <footer class="pkc-footer">
    <span>Piskee {$pk.version|escape:'html':'UTF-8'} · {$t.made_in|escape:'html':'UTF-8'} · <a href="{$pk.brand.website|escape:'html':'UTF-8'}" target="_blank" rel="noopener">piskee.com</a></span>
    <span>{$t.legal1|escape:'html':'UTF-8'}</span>
  </footer>
</div>
