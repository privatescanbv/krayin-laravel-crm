<#import "template.ftl" as layout>
<@layout.registrationLayout displayMessage=!messagesPerField.existsError('username','password'); section>

<#if section = "header">
    <#-- ${msg("loginAccountTitle")} -->
</#if>
<div class="log_main">
    <div class="log">
        <div class="right">

            <div class="l_logo">
               <a href="javascript:void(0)"><img src="${url.resourcesPath}/images/mainlogo.svg" alt="Logo"/></a>
            </div>

            <div class="form_h">
                <h1>Inloggen</h1>
                <span>Login op jouw persoonlijke account</span>
            </div>

            <#if messagesPerField.existsError('username','password')>
                <div class="alert-error" aria-live="polite">
                    ${kcSanitize(messagesPerField.getFirstError('username','password'))?no_esc}
                </div>
            <#elseif message?has_content && (message.type = 'error' || message.type = 'warning' || message.type = 'success' || message.type = 'info')>
                <div class="alert-${message.type}" aria-live="polite">
                    ${kcSanitize(message.summary)?no_esc}
                </div>
            </#if>

            <form id="kc-form-login"
                  action="${url.loginAction}"
                  method="post"
                  class="log_form">
 <div class="log_form">
                <label class="mail">
                    <span>E-mail</span>
                  <input type="text"
                         id="username"
                         name="username"
                         value="${(login.username!'')}"
                         placeholder="Vul je e-mailadres in"
                         class="input_box"
                         autocomplete="username"
                         autofocus
                         aria-invalid="<#if messagesPerField.existsError('username','password')>true</#if>" />
                </label>

                <label class="pword">
                    <span>Wachtwoord</span>
                 <input type="password"
                        id="password"
                        name="password"
                        placeholder="Vul je wachtwoord in"
                        class="input_box"
                        autocomplete="current-password"
                        aria-invalid="<#if messagesPerField.existsError('username','password')>true</#if>" />

                     <div class="icon">
                        <img
                            src="${url.resourcesPath}/images/close_eye.svg"
                            class="eye-icon"
                            data-target="password"
                            alt="Toon wachtwoord"
                            style="cursor:pointer;" />
                    </div>
                </label>

                <div class="check">
                    <label>
                        <input type="checkbox"
                               id="rememberMe"
                               name="rememberMe"
                               <#if login.rememberMe??>checked</#if> />
                        <strong>Onthoud mij</strong>
                    </label>

                    <#if realm.resetPasswordAllowed>
                        <a href="${url.loginResetCredentialsUrl}">
                            Wachtwoord vergeten?
                        </a>
                    </#if>
                </div>

                <div class="form_end">
                    <button type="submit"
                            class="input_box login_btn">
                        Login
                    </button>

                    <#if realm.registrationAllowed>
                        <span>OF</span>
                        <a href="${url.registrationUrl}" class="input_box">
                            Vraag een account aan
                        </a>
                    </#if>
                </div>
</div>
            </form>
        </div>

        <div class="log_end">
            <a class="button">
                <img src="${url.resourcesPath}/images/logo.svg" alt="Logo"/>
            </a>
            <a class="button">
                <img src="${url.resourcesPath}/images/hernia_logo.svg" alt="Hernia"/>
            </a>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.eye-icon').forEach(function (icon) {
        icon.addEventListener('click', function () {
            const input = document.getElementById(this.dataset.target);
            if (!input) return;

            if (input.type === 'password') {
                input.type = 'text';
                this.src = '${url.resourcesPath}/images/open_eye.svg';
            } else {
                input.type = 'password';
                this.src = '${url.resourcesPath}/images/close_eye.svg';
            }
        });
    });
});
</script>

</@layout.registrationLayout>
