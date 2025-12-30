<#import "template.ftl" as layout>
<#import "password-commons.ftl" as passwordCommons>

<@layout.registrationLayout displayMessage=!messagesPerField.existsError('password','password-confirm')>

<div class="log_main">
    <div class="log">
        <div class="right">

            <div class="form_h">
                <h1>Nieuw wachtwoord instellen</h1>
                <span>Kies een nieuw wachtwoord voor je account</span>
            </div>

            <form id="kc-passwd-update-form"
                  action="${url.loginAction}"
                  method="post"
                  class="log_form">

                <!-- Nieuw wachtwoord -->
                <label class="pword">
                    <span>${msg("passwordNew")}</span>
                    <input type="password"
                           id="password-new"
                           name="password-new"
                           class="input_box"
                           autocomplete="new-password"
                           autofocus
                           aria-invalid="<#if messagesPerField.existsError('password')>true</#if>" />

                    <#if messagesPerField.existsError('password')>
                        <span class="error">
                            ${kcSanitize(messagesPerField.get('password'))?no_esc}
                        </span>
                    </#if>
                </label>

                <!-- Bevestig wachtwoord -->
                <label class="pword">
                    <span>${msg("passwordConfirm")}</span>
                    <input type="password"
                           id="password-confirm"
                           name="password-confirm"
                           class="input_box"
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
