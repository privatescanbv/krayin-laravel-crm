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
                         autofocus />
                </label>

                <label class="pword">
                    <span>Wachtwoord</span>
                 <input type="password"
                        id="password"
                        name="password"
                        placeholder="Vul je wachtwoord in"
                        class="input_box"
                        autocomplete="current-password"/>

                    <div class="icon">
                        <img src="${url.resourcesPath}/images/close_eye.svg"
                             alt="Toon wachtwoord"
                             class="eye-icon"/>
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

</@layout.registrationLayout>
