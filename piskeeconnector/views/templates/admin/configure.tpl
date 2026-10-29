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
      <span class="pkc-langs">{foreach $pk.lang_urls as $code => $url}<a class="pkc-link-light{if $pk.lang == $code} is-active{/if}" href="{$url|escape:'html':'UTF-8'}" hreflang="{$code|escape:'html':'UTF-8'}" title="{$pk.lang_names[$code]|escape:'html':'UTF-8'}">{$code|upper|escape:'html':'UTF-8'}</a>{/foreach}</span>
    </div>
  </header>

  {if $pk.notice}
    <div class="pkc-notice pkc-notice--{$pk.notice_type|escape:'html':'UTF-8'}">{$pk.notice|escape:'html':'UTF-8'}</div>
  {/if}

  {if $pk.updates.free || $pk.updates.pro}
    <div class="pkc-updates">
      {if $pk.updates.free}
        <div class="pkc-update">
          <div class="pkc-update__text">
            <strong>{$t.upd_free_title|escape:'html':'UTF-8'} {$pk.updates.free.version|escape:'html':'UTF-8'}</strong>
            <span class="pkc-muted">{$t.upd_you_have|escape:'html':'UTF-8'} {$pk.updates.free.current|escape:'html':'UTF-8'}</span>
            {if $pk.updates.free.notes}<p class="pkc-update__notes">{$pk.updates.free.notes|escape:'html':'UTF-8'|nl2br}</p>{/if}
            <p class="pkc-update__how">{$t.upd_how|escape:'html':'UTF-8'}</p>
          </div>
          <a class="pkc-btn" href="{$pk.updates.free.download|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{$t.upd_download|escape:'html':'UTF-8'}</a>
        </div>
      {/if}
      {if $pk.updates.pro}
        <div class="pkc-update pkc-update--pro">
          <div class="pkc-update__text">
            <strong>{$t.upd_pro_title|escape:'html':'UTF-8'} {$pk.updates.pro.version|escape:'html':'UTF-8'}</strong>
            <span class="pkc-muted">{$t.upd_you_have|escape:'html':'UTF-8'} {$pk.updates.pro.current|escape:'html':'UTF-8'}</span>
            <p class="pkc-update__how">{$t.upd_pro_how|escape:'html':'UTF-8'}</p>
          </div>
          <form method="post" action="{$pk.action_url|escape:'html':'UTF-8'}">
            <input type="hidden" name="piskee_token" value="{$pk.token|escape:'html':'UTF-8'}">
            <input type="hidden" name="piskee_action" value="download_pro">
            <button type="submit" class="pkc-btn">{$t.upd_download|escape:'html':'UTF-8'}</button>
          </form>
        </div>
      {/if}
    </div>
  {/if}

  <nav class="pkc-tabs" role="tablist">
    <button type="button" class="pkc-tab is-active" data-tab="connect">{$t.tab_connect|escape:'html':'UTF-8'}</button>
    <button type="button" class="pkc-tab" data-tab="tools">{$t.tab_tools|escape:'html':'UTF-8'} <span class="pkc-count">{$pk.tool_count|intval}</span></button>
    <button type="button" class="pkc-tab" data-tab="theme">{$t.tab_theme|escape:'html':'UTF-8'}</button>
    <button type="button" class="pkc-tab" data-tab="docs">{$t.tab_docs|escape:'html':'UTF-8'}</button>
    <button type="button" class="pkc-tab" data-tab="history">{$t.tab_history|escape:'html':'UTF-8'} <span class="pkc-pro-tag" title="Piskee Pro">&#9733; PRO</span>{if $pk.history.state == 'ok' && $pk.history.total > 0} <span class="pkc-count">{$pk.history.total|intval}</span>{/if}</button>
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
            <li>{$t.w2|escape:'html':'UTF-8'}</li>
            <li>{$t.w1|escape:'html':'UTF-8'}</li>
            <li>{$t.w3|escape:'html':'UTF-8'}</li>
            <li>{$t.w4|escape:'html':'UTF-8'}</li>
            <li>{$t.w5|escape:'html':'UTF-8'}</li>
            <li>{$t.w6|escape:'html':'UTF-8'}</li>
            <li>{$t.w7|escape:'html':'UTF-8'}</li>
            <li>{$t.w8|escape:'html':'UTF-8'}</li>
            <li class="pkc-pro-item"><span class="pkc-badge-pro">Pro</span> {$t.w9|escape:'html':'UTF-8'}</li>
            <li>{$t.w10|escape:'html':'UTF-8'}</li>
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
            {foreach $pk.lang_names as $code => $name}<button type="button" class="pkc-chip {if $pk.lang == $code}is-active{/if}" data-doc="{$code|escape:'html':'UTF-8'}">{$name|escape:'html':'UTF-8'}</button>{/foreach}
          </div>
          {foreach $pk.docs as $code => $html}<article class="pkc-doc__body {if $pk.lang == $code}is-active{/if}" data-doc-body="{$code|escape:'html':'UTF-8'}">{$html nofilter}</article>{/foreach}
        </div>
      </section>

            {* ---------- History (Pro) ---------- *}
      <section class="pkc-panel" data-panel="history">
        <div class="pkc-card pkc-hist-intro">
          <div>
            <h2>{$t.h_title|escape:'html':'UTF-8'}</h2>
            <p class="pkc-muted">{$t.h_help|escape:'html':'UTF-8'}</p>
          </div>
          {if $pk.history.state == 'ok'}
            <div class="pkc-hist-stat"><strong>{$pk.history.total|intval}</strong><span>{$t.h_stat|escape:'html':'UTF-8'}</span></div>
          {/if}
        </div>

        {if $pk.history.state == 'locked'}
          <div class="pkc-card pkc-card--pro pkc-hist-locked">
            <p class="pkc-audit__kicker">Piskee Pro</p>
            <h2>{$t.h_locked_title|escape:'html':'UTF-8'}</h2>
            <ul class="pkc-hist-points">
              <li>{$t.h_locked_1|escape:'html':'UTF-8'}</li>
              <li>{$t.h_locked_2|escape:'html':'UTF-8'}</li>
              <li>{$t.h_locked_3|escape:'html':'UTF-8'}</li>
            </ul>
            <div class="pkc-hist-demo" aria-hidden="true">
              <div class="pkc-hist-row"><span class="pkc-hist-time">10:42</span><span><strong>{$t.h_demo_object|escape:'html':'UTF-8'}</strong><br><code>meta_title (fr)</code> <s>T-shirt</s> &rarr; T-shirt imprimé coton bio</span><span class="pkc-btn pkc-btn--small pkc-btn--ghost">&#8634; {$t.h_undo|escape:'html':'UTF-8'}</span></div>
            </div>
            <button type="button" class="pkc-btn" data-open-tab="pro">{$t.pro_cta|escape:'html':'UTF-8'}</button>
          </div>
        {elseif $pk.history.state == 'no_module'}
          <div class="pkc-card pkc-card--warn">
            <h2>{$t.h_install_title|escape:'html':'UTF-8'}</h2>
            <p>{$t.h_install_text|escape:'html':'UTF-8'}</p>
          </div>
        {elseif $pk.history.sessions|count == 0}
          <div class="pkc-card"><p class="pkc-muted">{$t.h_empty|escape:'html':'UTF-8'}</p></div>
        {else}
          {foreach $pk.history.sessions as $session}
            <div class="pkc-card pkc-hist-session">
              <div class="pkc-hist-head">
                <div>
                  <strong>{$t.h_session|escape:'html':'UTF-8'} {$session.day|escape:'html':'UTF-8'}</strong>
                  <span class="pkc-muted">{$session.from|escape:'html':'UTF-8'} &rarr; {$session.to|escape:'html':'UTF-8'} · {$session.entries|count} {$t.h_changes|escape:'html':'UTF-8'}</span>
                </div>
                {if $pk.history.can_undo && $session.open > 0}
                  <form method="post" action="{$pk.action_url|escape:'html':'UTF-8'}" class="pkc-inline-form" onsubmit="return confirm(this.getAttribute('data-confirm'));" data-confirm="{$t.h_confirm|escape:'html':'UTF-8'}">
                    <input type="hidden" name="piskee_token" value="{$pk.token|escape:'html':'UTF-8'}">
                    <input type="hidden" name="piskee_action" value="undo_session">
                    <input type="hidden" name="history_session" value="{$session.ref|escape:'html':'UTF-8'}">
                    <button type="submit" class="pkc-btn pkc-btn--small pkc-btn--ghost">&#8634; {$t.h_undo_session|escape:'html':'UTF-8'}</button>
                  </form>
                {/if}
              </div>
              <div class="pkc-hist-list">
                {foreach $session.entries as $e}
                  <div class="pkc-hist-row{if $e.undone} is-undone{/if}">
                    <span class="pkc-hist-time">{$e.time|escape:'html':'UTF-8'}<small>{$e.tool|escape:'html':'UTF-8'}</small></span>
                    <span class="pkc-hist-what">
                      <strong>{if isset($t["hobj_`$e.object_key`"])}{$t["hobj_`$e.object_key`"]|escape:'html':'UTF-8'}{else}{$e.object|escape:'html':'UTF-8'}{/if} #{$e.object_id|intval}</strong>{if $e.label} · {$e.label|truncate:70:'…'|escape:'html':'UTF-8'}{/if}
                      {if $e.action == 'update'}
                        {foreach $e.changes as $c}
                          <span class="pkc-hist-change"><code>{$c.field|escape:'html':'UTF-8'}</code> <s>{if $c.before === ''}&empty;{else}{$c.before|escape:'html':'UTF-8'}{/if}</s> &rarr; {if $c.after === ''}&empty;{else}{$c.after|escape:'html':'UTF-8'}{/if}</span>
                        {/foreach}
                        {if $e.more > 0}<span class="pkc-muted pkc-hist-change">+ {$e.more|intval} {$t.h_more|escape:'html':'UTF-8'}</span>{/if}
                      {else}
                        <span class="pkc-hist-badge pkc-hist-badge--{$e.action|escape:'html':'UTF-8'}">{if $e.action == 'add'}{$t.h_add|escape:'html':'UTF-8'}{else}{$t.h_delete|escape:'html':'UTF-8'}{/if}</span>
                      {/if}
                    </span>
                    <span class="pkc-hist-act">
                      {if $e.undone}
                        <span class="pkc-hist-badge">{$t.h_is_undone|escape:'html':'UTF-8'}</span>
                      {elseif $pk.history.can_undo && $e.can_undo}
                        <form method="post" action="{$pk.action_url|escape:'html':'UTF-8'}" class="pkc-inline-form">
                          <input type="hidden" name="piskee_token" value="{$pk.token|escape:'html':'UTF-8'}">
                          <input type="hidden" name="piskee_action" value="undo_change">
                          <input type="hidden" name="history_id" value="{$e.id|intval}">
                          <button type="submit" class="pkc-btn pkc-btn--small pkc-btn--ghost">&#8634; {$t.h_undo|escape:'html':'UTF-8'}</button>
                        </form>
                      {/if}
                    </span>
                  </div>
                {/foreach}
              </div>
            </div>
          {/foreach}
          <p class="pkc-muted pkc-hist-foot">{$t.h_foot|escape:'html':'UTF-8'}</p>
        {/if}
      </section>

{* ---------- Free / Pro ---------- *}
      <section class="pkc-panel" data-panel="pro">
        <div class="pkc-card">
          <h2>{$t.plan_current|escape:'html':'UTF-8'}{if $pk.lang == 'fr'}&nbsp;:{else}:{/if} {if $pk.plan.license.valid}{$t.plan_pro|escape:'html':'UTF-8'}{else}{$t.plan_free|escape:'html':'UTF-8'}{/if}</h2>
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
              <tr><td>{$t.row_history|escape:'html':'UTF-8'}</td><td class="no">&#8212;</td><td class="yes">&#10003;</td></tr>
              <tr><td>{$t.row_geo|escape:'html':'UTF-8'}</td><td class="no">&#8212;</td><td class="yes">&#10003;</td></tr>
              <tr><td>{$t.row_megamenu|escape:'html':'UTF-8'}</td><td class="no">&#8212;</td><td class="yes">&#10003;</td></tr>
              <tr><td>{$t.row_redirects|escape:'html':'UTF-8'}</td><td class="no">&#8212;</td><td class="yes">&#10003;</td></tr>
              <tr><td>{$t.row_refunds|escape:'html':'UTF-8'}</td><td class="no">&#8212;</td><td class="yes">&#10003;</td></tr>
              <tr><td>{$t.row_growth|escape:'html':'UTF-8'}</td><td class="no">&#8212;</td><td class="yes">&#10003;</td></tr>
              <tr><td>{$t.row_reviews|escape:'html':'UTF-8'}</td><td class="no">&#8212;</td><td class="yes">&#10003;</td></tr>
              <tr><td>{$t.row_preview|escape:'html':'UTF-8'}</td><td class="no">&#8212;</td><td class="yes">&#10003;</td></tr>
              <tr><td>{$t.row_newsletter|escape:'html':'UTF-8'}</td><td class="no">&#8212;</td><td class="yes">&#10003;</td></tr>
              <tr><td>{$t.row_multistore|escape:'html':'UTF-8'}</td><td class="no">&#8212;</td><td class="yes">&#10003;</td></tr>
              <tr><td>{$t.row_support|escape:'html':'UTF-8'}</td><td class="no">&#8212;</td><td class="yes">&#10003;</td></tr>
            </tbody>
          </table>
        </div>

        <div class="pkc-pricing">
          <div class="pkc-card pkc-card--pro pkc-billing" data-billing="year">
            <div class="pkc-billing__head">
              <p class="pkc-audit__kicker">{$t.plan_pro|escape:'html':'UTF-8'}</p>
              <div class="pkc-billing__switch" role="tablist">
                <button type="button" class="pkc-billing__opt" data-billing-set="month" role="tab">{$t.bill_month|escape:'html':'UTF-8'}</button>
                <button type="button" class="pkc-billing__opt is-active" data-billing-set="year" role="tab">{$t.bill_year|escape:'html':'UTF-8'} <span class="pkc-billing__save">-17&nbsp;%</span></button>
              </div>
            </div>
            <div class="pkc-billing__panel" data-billing-panel="year">
              <span class="pkc-billing__badge">{$t.save_badge|escape:'html':'UTF-8'}</span>
              <p class="pkc-billing__price"><span class="pkc-billing__amount">{$t.py_amount|escape:'html':'UTF-8'}</span> <span class="pkc-billing__per">{$t.py_per|escape:'html':'UTF-8'}</span> <s class="pkc-billing__was">{$t.py_was|escape:'html':'UTF-8'}</s></p>
              <p class="pkc-billing__equiv">{$t.py_equiv|escape:'html':'UTF-8'}</p>
            </div>
            <div class="pkc-billing__panel" data-billing-panel="month" hidden>
              <p class="pkc-billing__price"><span class="pkc-billing__amount">{$t.pm_amount|escape:'html':'UTF-8'}</span> <span class="pkc-billing__per">{$t.pm_per|escape:'html':'UTF-8'}</span></p>
              <p class="pkc-billing__equiv">{$t.pm_equiv|escape:'html':'UTF-8'}</p>
            </div>
            <p class="pkc-billing__launch">{$t.launch_note|escape:'html':'UTF-8'}</p>
            <p class="pkc-muted">{$t.pro_note|escape:'html':'UTF-8'}</p>
            <a class="pkc-btn pkc-btn--block" href="{$pk.brand.buy_year|escape:'html':'UTF-8'}" data-href-month="{$pk.brand.buy_month|escape:'html':'UTF-8'}" data-href-year="{$pk.brand.buy_year|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{$t.pro_cta|escape:'html':'UTF-8'}</a>
          </div>
          <div class="pkc-card">
            <p class="pkc-audit__kicker">{$t.onboarding_title|escape:'html':'UTF-8'}</p>
            <p class="pkc-plan"><span class="pkc-plan__price">{$pk.plan.onboarding|escape:'html':'UTF-8'}</span></p>
            <p class="pkc-muted">{$t.onboarding_text|escape:'html':'UTF-8'}</p>
            <a class="pkc-btn pkc-btn--ghost pkc-btn--block" href="{$pk.brand.buy_onboarding|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{$t.onboarding_cta|escape:'html':'UTF-8'}</a>
          </div>
        </div>
        <div class="pkc-card pkc-extra">
          <div class="pkc-extra__text">
            <p class="pkc-audit__kicker">{$t.extra_title|escape:'html':'UTF-8'}</p>
            <p class="pkc-extra__price"><strong>{$t.extra_amount|escape:'html':'UTF-8'}</strong> {$t.extra_per|escape:'html':'UTF-8'}</p>
            <p class="pkc-muted">{$t.extra_text|escape:'html':'UTF-8'}</p>
          </div>
          <a class="pkc-btn pkc-btn--ghost" href="{$pk.brand.buy_extra|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{$t.extra_cta|escape:'html':'UTF-8'}</a>
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
        <h2>{$t.plan_current|escape:'html':'UTF-8'}{if $pk.lang == 'fr'}&nbsp;:{else}:{/if} {if $pk.plan.license.valid}{$t.plan_pro|escape:'html':'UTF-8'}{else}{$t.plan_free|escape:'html':'UTF-8'}{/if}</h2>
        {if $pk.plan.license.valid}
          <p class="pkc-muted">{$t.plan_valid|escape:'html':'UTF-8'} <strong>{$pk.plan.license.expires|escape:'html':'UTF-8'}</strong>.</p>
        {else}
          <div class="pkc-usage">
            <div class="pkc-usage__bar"><div class="pkc-usage__fill {if $pk.plan.percent >= 80}is-high{/if}" style="width: {$pk.plan.percent|intval}%"></div></div>
            <div class="pkc-usage__label"><span><strong>{$pk.plan.used|intval} / {$pk.plan.allowance|intval}</strong> {$t.plan_usage|escape:'html':'UTF-8'}</span></div>
          </div>
          <p class="pkc-muted">{$t.plan_reads|escape:'html':'UTF-8'}</p>
          <p class="pkc-plan-card__offer">{$t.plan_year_hint|escape:'html':'UTF-8'}</p>
          <button type="button" class="pkc-btn pkc-btn--block" data-open-tab="pro">{$t.pro_cta|escape:'html':'UTF-8'}</button>
        {/if}
      </div>
      <div class="pkc-card pkc-onboard">
        <p class="pkc-onboard__kicker">{$t.ob_kicker|escape:'html':'UTF-8'}</p>
        <h2 class="pkc-onboard__title">{$t.ob_title|escape:'html':'UTF-8'}</h2>
        <p class="pkc-onboard__text">{$t.ob_text|escape:'html':'UTF-8'}</p>
        <ul class="pkc-onboard__list">
          <li>{$t.ob_b1|escape:'html':'UTF-8'}</li>
          <li>{$t.ob_b2|escape:'html':'UTF-8'}</li>
          <li>{$t.ob_b3|escape:'html':'UTF-8'}</li>
        </ul>
        <div class="pkc-onboard__price">
          <span class="pkc-onboard__amount">{$t.ob_amount|escape:'html':'UTF-8'}</span>
          <span class="pkc-onboard__tax">{$t.ob_tax|escape:'html':'UTF-8'}</span>
        </div>
        <p class="pkc-onboard__unit">{$t.ob_unit|escape:'html':'UTF-8'}</p>
        <a class="pkc-btn pkc-btn--cream pkc-btn--block" href="{$pk.brand.buy_onboarding|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{$t.ob_cta|escape:'html':'UTF-8'}</a>
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
