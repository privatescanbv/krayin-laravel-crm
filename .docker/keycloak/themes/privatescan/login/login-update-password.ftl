<#import "template.ftl" as layout>
<#import "password-commons.ftl" as passwordCommons>

<@layout.registrationLayout displayMessage=true>

<div class="log_main">
    <div class="log">
        <div class="right">

            <div class="form_h">
                <h1>Nieuw wachtwoord instellen</h1>
                <span>Kies een sterk wachtwoord voor je account.</span>
            </div>

            <div class="password-requirements" aria-label="Wachtwoordeisen">
                <p class="password-requirements__title">Je wachtwoord moet bevatten:</p>
                <ul>
                    <li>Minimaal 8 tekens</li>
                    <li>Minimaal 1 hoofdletter (A-Z)</li>
                    <li>Minimaal 1 cijfer (0-9)</li>
                    <li>Minimaal 1 speciaal teken (! @ # $ % & *)</li>
                </ul>
            </div>

            <form id="kc-passwd-update-form"
                  action="${url.loginAction}"
                  method="post"
                  class="log_form">

                <label class="pword">
                    <span>Wachtwoord</span>
                    <input type="password"
                           id="password-new"
                           name="password-new"
                           class="input_box"
                           placeholder="Voer je nieuwe wachtwoord in"
                           autocomplete="new-password"
                           autofocus
                           aria-invalid="<#if messagesPerField.existsError('password')>true</#if>" />

                    <#if messagesPerField.existsError('password')>
                        <span class="error">
                            ${kcSanitize(messagesPerField.get('password'))?no_esc}
                        </span>
                    </#if>
                </label>

                <label class="pword">
                    <span>Bevestigen</span>
                    <input type="password"
                           id="password-confirm"
                           name="password-confirm"
                           class="input_box"
                           placeholder="Herhaal je wachtwoord"
                           autocomplete="new-password"
                           aria-invalid="<#if messagesPerField.existsError('password-confirm')>true</#if>" />

                    <#if messagesPerField.existsError('password-confirm')>
                        <span class="error">
                            ${kcSanitize(messagesPerField.get('password-confirm'))?no_esc}
                        </span>
                    </#if>
                </label>

                <!-- Andere sessies uitloggen -->
                <@passwordCommons.logoutOtherSessions/>

                <div class="form_end">
                    <button type="submit" class="input_box login_btn">
                        ${msg("doSubmit")}
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

</@layout.registrationLayout>
