<?php

declare(strict_types=1);

set_exception_handler(function (Throwable $e): void {
    $message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    $class   = htmlspecialchars(get_class($e), ENT_QUOTES, 'UTF-8');
    http_response_code(500);
    echo <<<HTML
    <!doctype html>
    <html lang="cs">
    <head>
        <meta charset="utf-8">
        <title>Chyba</title>
        <style>
            body { font-family: sans-serif; background: #1a1a2e; color: #eee; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
            .box { background: #16213e; border: 1px solid #e05252; border-radius: 8px; padding: 2rem 2.5rem; max-width: 600px; width: 100%; }
            h1 { margin: 0 0 .5rem; font-size: 1.3rem; color: #ff7b7b; }
            p  { margin: 0; color: #ccc; word-break: break-word; }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>$class</h1>
            <p>$message</p>
        </div>
    </body>
    </html>
    HTML;
});

$bl_config = require_once __DIR__ . '/badlogin.php';
$db_config = require __DIR__ . '/../.env/db_config.php';
require_once __DIR__ . '/db_connect.php';

/**
 * Escapes output for safe HTML rendering.
 *
 * @param string|null $value Raw value.
 *
 * @return string HTML-escaped string.
 */
function showroom_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Appends a flash-style message to the message list.
 *
 * @param array  $messages Message list passed by reference.
 * @param string $type     Message type.
 * @param string $text     Message text.
 *
 * @return void
 */
function showroom_add_message(array &$messages, string $type, string $text): void
{
    $messages[] = [
        'type' => $type,
        'text' => $text,
    ];
}

/**
 * Stores the current login timestamp in the session.
 *
 * @return void
 */
function showroom_set_login_timestamp(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $_SESSION['showroom_login_at'] = date('c');
}

/**
 * Reads the stored login timestamp from the session.
 *
 * @return string|null Login timestamp, or null when unavailable.
 */
function showroom_get_login_timestamp(): ?string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    return isset($_SESSION['showroom_login_at']) ? (string) $_SESSION['showroom_login_at'] : null;
}

/**
 * Removes the stored login timestamp from the session.
 *
 * @return void
 */
function showroom_clear_login_timestamp(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    unset($_SESSION['showroom_login_at']);
}

/**
 * Determines which modal should stay open after a submitted action.
 *
 * @param string $action Submitted action name.
 *
 * @return string Modal identifier.
 */
function showroom_modal_state_for_action(string $action): string
{
    return match ($action) {
        'register' => 'register',
        'login_password', 'request_linktoken' => 'login',
        default => '',
    };
}

$messages = [];
$open_modal = '';

try {
    $pdo = getDBConnection($db_config);
} catch (Throwable $e) {
    $pdo = null;
    showroom_add_message($messages, 'error', $e instanceof Bl_Exception ? $e->bl_full_chain_message() : $e->getMessage());
}

$current_user = null;
if ($pdo instanceof PDO) {
    try {
        $current_user = bl_auth_current_user_db_data($pdo, $bl_config);
    } catch (Throwable $e) {
        showroom_add_message($messages, 'error', $e instanceof Bl_Exception ? $e->bl_full_chain_message() : $e->getMessage());
    }
}

if ($pdo instanceof PDO) {
    $token_key = (string) bl_config_get('_linktoken_get_key', $bl_config);
    $token_from_url = isset($_GET[$token_key]) ? trim((string) $_GET[$token_key]) : '';

    if ($token_from_url !== '' && $current_user === null) {
        try {
            $user = bl_auth_login_with_linktoken($token_from_url, $pdo, $bl_config);
            if ($user !== null) {
                showroom_set_login_timestamp();
                $current_user = $user;
                showroom_add_message($messages, 'success', 'Přihlášení přes odkaz z e-mailu proběhlo úspěšně.');
            } else {
                showroom_add_message($messages, 'error', 'Přihlašovací token je neplatný nebo expirovaný.');
            }
        } catch (Throwable $e) {
            showroom_add_message($messages, 'error', $e instanceof Bl_Exception ? $e->bl_full_chain_message() : $e->getMessage());
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo instanceof PDO) {
    $action = (string) ($_POST['action'] ?? '');
    $open_modal = showroom_modal_state_for_action($action);

    try {
        switch ($action) {
            case 'register':
                $email = (string) ($_POST['register_email'] ?? '');
                $with_password = isset($_POST['register_with_password']);
                $password = $with_password ? (string) ($_POST['register_password'] ?? '') : null;
                $user_id = bl_auth_register($email, $password !== '' ? $password : null, $pdo, $bl_config);
                showroom_add_message($messages, 'success', 'Účet byl vytvořen. ID uživatele: ' . $user_id . '.');
                break;

            case 'login_password':
                $email = (string) ($_POST['login_email'] ?? '');
                $password = (string) ($_POST['login_password'] ?? '');
                $current_user = bl_auth_login_with_password($email, $password, $pdo, $bl_config);
                showroom_set_login_timestamp();
                showroom_add_message($messages, 'success', 'Přihlášení heslem proběhlo úspěšně.');
                $open_modal = '';
                break;

            case 'request_linktoken':
                $email = (string) ($_POST['token_email'] ?? '');
                if (bl_auth_request_linktoken_login($email, $pdo, $bl_config)) {
                    showroom_add_message($messages, 'success', 'Pokud je e-mail správný a odesílání pošty funguje, byl odeslán přihlašovací odkaz.');
                }else{
                    showroom_add_message($messages, 'error', 'Nepodařilo se odeslat přihlašovací odkaz. Zkontrolujte nastavení pošty a znovu to zkuste.');
                }
                
                break;

            case 'set_password':
                if ($current_user === null) {
                    throw new Bl_Exception(Bl_Exception::USER_ERROR, 'Nejste přihlášen.');
                }

                $new_password = (string) ($_POST['new_password'] ?? '');
                if ($new_password === '') {
                    throw new Bl_Exception(Bl_Exception::USER_ERROR, 'Nové heslo nesmí být prázdné.');
                }

                $password_hash = bl_auth_hash_password($new_password);
                $saved = bl_db_set_password_hash((int) $current_user['id'], $password_hash, $pdo, $bl_config);

                if (!$saved) {
                    throw new Bl_Exception(Bl_Exception::SYSTEM_ERROR, 'Nepodařilo se uložit nové heslo.');
                }

                showroom_add_message($messages, 'success', 'Nové heslo bylo nastaveno.');
                $current_user = bl_auth_current_user_db_data($pdo, $bl_config);
                break;

            case 'logout':
                bl_auth_logout();
                showroom_clear_login_timestamp();
                $current_user = null;
                showroom_add_message($messages, 'success', 'Byli jste odhlášeni.');
                break;

            case 'delete_account':
                if (!bl_auth_delete_current_user($pdo, $bl_config)) {
                    throw new Bl_Exception(Bl_Exception::SYSTEM_ERROR, 'Účet se nepodařilo zrušit.');
                }
                showroom_clear_login_timestamp();
                $current_user = null;
                showroom_add_message($messages, 'success', 'Účet byl zrušen.');
                break;
        }
    } catch (Throwable $e) {
        showroom_add_message($messages, 'error', $e instanceof Bl_Exception ? $e->getMessage() : $e->getMessage());
    }
}

$login_at = showroom_get_login_timestamp();
$token_key = $pdo instanceof PDO ? (string) bl_config_get('_linktoken_get_key', $bl_config) : 'token';
$linktoken_hint_url = $pdo instanceof PDO ? bl_linktoken_build_login_url('TOKEN_Z_EMAILU', $bl_config) : '';
?>

<!doctype html>
<html lang="cs">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BadLogin showroom</title>
    <style>
        :root {
            --bg: #0b1020;
            --panel: #141b34;
            --text: #eef3ff;
            --muted: #aeb9d6;
            --line: #2b3765;
            --accent: #6ea8fe;
            --accent-2: #8ff0c6;
            --danger: #ff7b7b;
            --shadow: 0 12px 40px rgba(0, 0, 0, .28)
        }

        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            font-family: Inter, Arial, sans-serif;
            background: linear-gradient(180deg, #0a1021 0%, #101734 100%);
            color: var(--text)
        }

        .wrap {
            max-width: 1120px;
            margin: 0 auto;
            padding: 24px
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 28px
        }

        .brand {
            font-size: 28px;
            font-weight: 800
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, .05);
            border: 1px solid var(--line);
            border-radius: 999px;
            padding: 10px 14px
        }

        .hero,
        .card {
            background: rgba(20, 27, 52, .9);
            border: 1px solid var(--line);
            border-radius: 20px;
            box-shadow: var(--shadow)
        }

        .hero {
            padding: 24px;
            margin-bottom: 18px
        }

        .hero h1 {
            margin: 0 0 10px;
            font-size: 34px
        }

        .hero p {
            color: var(--muted);
            line-height: 1.55
        }

        .hero-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 18px
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 18px
        }

        .card {
            padding: 20px
        }

        h2,
        h3 {
            margin-top: 0
        }

        .note {
            background: rgba(110, 168, 254, .08);
            border: 1px solid rgba(110, 168, 254, .2);
            border-radius: 14px;
            padding: 16px;
            color: var(--muted)
        }

        .messages {
            margin: 0 0 18px;
            display: grid;
            gap: 10px
        }

        .msg {
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid
        }

        .msg.success {
            background: rgba(143, 240, 198, .1);
            border-color: rgba(143, 240, 198, .35);
            color: #d9fff0
        }

        .msg.error {
            background: rgba(255, 123, 123, .1);
            border-color: rgba(255, 123, 123, .35);
            color: #ffe2e2
        }

        .btn {
            appearance: none;
            border: 0;
            border-radius: 12px;
            padding: 11px 16px;
            font: inherit;
            color: #07101f;
            background: var(--accent);
            cursor: pointer;
            font-weight: 700
        }

        .btn.secondary {
            background: transparent;
            color: var(--text);
            border: 1px solid var(--line)
        }

        .btn.success {
            background: var(--accent-2)
        }

        .btn.danger {
            background: var(--danger)
        }

        .btn.linklike {
            background: transparent;
            color: var(--accent);
            border: 0;
            padding: 0
        }

        .kv {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 8px 12px
        }

        .kv div:nth-child(odd) {
            color: var(--muted)
        }

        code,
        pre {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            background: rgba(255, 255, 255, .05);
            border-radius: 10px
        }

        code {
            padding: 3px 6px
        }

        pre {
            padding: 14px;
            overflow: auto;
            color: #dbe7ff
        }

        input[type=email],
        input[type=password],
        input[type=text] {
            width: 100%;
            background: #0d1430;
            color: var(--text);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 12px 14px;
            font: inherit
        }

        label {
            display: block;
            margin: 12px 0 8px;
            color: var(--muted)
        }

        .checkbox {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 12px;
            color: var(--muted)
        }

        .checkbox input {
            width: auto
        }

        .inline-note {
            color: var(--muted);
            font-size: .95rem;
            margin-top: 10px
        }

        .modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(3, 7, 18, .68);
            z-index: 50
        }

        .modal.open {
            display: flex
        }

        .modal-panel {
            width: min(560px, 100%);
            background: #111936;
            border: 1px solid var(--line);
            border-radius: 22px;
            box-shadow: var(--shadow);
            overflow: hidden
        }

        .modal-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 20px;
            border-bottom: 1px solid var(--line)
        }

        .modal-body {
            padding: 20px
        }

        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px
        }

        .tab {
            flex: 1;
            padding: 10px 12px;
            border: 1px solid var(--line);
            background: #0d1430;
            color: var(--text);
            border-radius: 12px;
            cursor: pointer
        }

        .tab.active {
            background: rgba(110, 168, 254, .16);
            border-color: rgba(110, 168, 254, .45)
        }

        .tab-panel {
            display: none
        }

        .tab-panel.active {
            display: block
        }

        .footer-note {
            margin-top: 20px;
            color: var(--muted);
            font-size: .95rem
        }

        @media (max-width:700px) {
            .topbar {
                align-items: flex-start;
                flex-direction: column
            }

            .kv {
                grid-template-columns: 1fr
            }
        }
    </style>
</head>

<body data-open-modal="<?= showroom_h($open_modal) ?>">
    <div class="wrap">
        <div class="topbar">
            <div class="brand">Ukázka použití BadLogin modulu</div>
            <div class="pill">
                <?php if ($current_user !== null): ?><strong>
                    <?= showroom_h((string)$current_user['email']) ?>
                </strong>
                <?php else: ?><button class="btn linklike" type="button" data-open="login">Přihlásit se</button>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($messages !== []): ?>
        <div class="messages">
            <?php foreach ($messages as $message): ?>
            <div class="msg <?= showroom_h($message['type']) ?>">
                <?= showroom_h($message['text']) ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <section class="hero">
            <h1>Info o BadLogin</h1>
            <p>Tato stránka používá nahraný modul BadLogin, jeho konfigurační soubor a pomocné soubory pro vytvoření PDO
                připojení. Modul načítá konfiguraci z <code>bl_config.php</code>, připojení se vytváří funkcí
                <code>getDBConnection()</code> z <code>db_connect.php</code> a ukázka očekává připravenou databázi i
                správně vyplněné přístupové údaje v <code>db_config.php</code>.</p>
            <div class="note">Než showroom spustíš, je potřeba vytvořit databázovou tabulku pro BadLogin (název musí sedět s tím v bl_config.php) a nastavit reálné
                PDO připojení. V ukázkovém <code>db_config.php</code> jsou zatím placeholder hodnoty pro PostgreSQL
                server, port, databázi, uživatele a heslo.</div>
            <div class="hero-actions">
                <?php if ($current_user === null): ?><button class="btn" type="button" data-open="login">Přihlásit
                    se</button><button class="btn secondary" type="button" data-open="register">Vytvořit účet</button>
                <?php else: ?>
                <form method="post"><input type="hidden" name="action" value="logout"><button class="btn secondary"
                        type="submit">Odhlásit se</button></form>
                <?php endif; ?>
            </div>
        </section>
        <div class="grid">
            <section class="card">
                <h2>Stav přihlášení</h2>
                <?php if ($current_user === null): ?>
                <p>Momentálně není nikdo přihlášen.</p>
                <p class="inline-note">Vpravo nahoře se v tomto stavu zobrazuje odkaz <strong>Přihlásit se</strong>.</p>
                <?php else: ?>
                <div class="kv">
                    <div>E-mail</div>
                    <div>
                        <?= showroom_h((string)$current_user['email']) ?>
                    </div>
                    <div>User ID</div>
                    <div>
                        <?= showroom_h((string)$current_user['id']) ?>
                    </div>
                    <div>Přihlášen od</div>
                    <div>
                        <?= showroom_h($login_at !== null ? date('d.m.Y H:i:s', strtotime($login_at)) : 'neuvedeno') ?>
                    </div>
                    <div>E-mail ověřen</div>
                    <div>
                        <?= !empty($current_user['is_email_verified']) ? 'ano' : 'ne' ?>
                    </div>
                    <div>Vytvořen</div>
                    <div>
                        <?= showroom_h((string)$current_user['created_at']) ?>
                    </div>
                </div>
                <?php endif; ?>
            </section>
            <section class="card">
                <h2>Informace o účtu z DB</h2>
                <?php if ($current_user === null): ?>
                <p>Po přihlášení se zde zobrazí data načtená funkcí <code>bl_auth_current_user_db_data()</code>.</p>
                <?php else: ?>
                <pre><?= showroom_h(print_r($current_user, true)) ?></pre>
                <?php endif; ?>
            </section>
            <section class="card">
                <h2>Nastavení účtu</h2>
                <?php if ($current_user === null): ?>
                <p>Nejprve se přihlas.</p>
                <?php else: ?>
                <form method="post"><input type="hidden" name="action" value="set_password"><label
                        for="new_password">Nové heslo</label><input id="new_password" type="password"
                        name="new_password" required>
                    <p class="inline-note">Tato ukázka umožňuje nastavit nové heslo bez zadání starého.</p><button
                        class="btn success" type="submit">Nastavit nové heslo</button>
                </form>
                <?php endif; ?>
            </section>
            <section class="card">
                <h2>Zrušení účtu</h2>
                <?php if ($current_user === null): ?>
                <p>Nejprve se přihlas.</p>
                <?php else: ?>
                <form method="post" onsubmit="return confirm('Opravdu chcete zrušit tento účet?');"><input type="hidden"
                        name="action" value="delete_account"><button class="btn danger" type="submit">Zrušit
                        účet</button></form>
                <p class="inline-note">Po zrušení účtu dojde i k odhlášení.</p>
                <?php endif; ?>
            </section>
            <section class="card">
                <h2>Přihlášení odkazem do e-mailu</h2>
                <p>Modul používá token v URL parametru <code><?= showroom_h($token_key) ?></code>.</p>
                <pre><?= showroom_h($linktoken_hint_url) ?></pre>
            </section>
        </div>
    </div>
    <div class="modal" id="modal-register" aria-hidden="true">
        <div class="modal-panel">
            <div class="modal-head">
                <h3>Vytvořit účet</h3><button class="btn secondary" type="button" data-close>×</button>
            </div>
            <div class="modal-body">
                <form method="post"><input type="hidden" name="action" value="register"><label
                        for="register_email">E-mail</label><input id="register_email" type="email" name="register_email"
                        required><label class="checkbox"><input type="checkbox" name="register_with_password"
                            id="register_with_password"> Zadat heslo už při registraci</label>
                    <div id="register-password-wrap" hidden><label for="register_password">Heslo</label><input
                            id="register_password" type="password" name="register_password"></div>
                    <p class="inline-note">Pokud heslo nezadáš, účet vznikne bez hesla a přihlašování bude možné odkazem
                        do e-mailu.</p><button class="btn" type="submit">Vytvořit účet</button>
                </form>
            </div>
        </div>
    </div>
    <div class="modal" id="modal-login" aria-hidden="true">
        <div class="modal-panel">
            <div class="modal-head">
                <h3>Přihlášení</h3><button class="btn secondary" type="button" data-close>×</button>
            </div>
            <div class="modal-body">
                <div class="tabs"><button class="tab active" type="button" data-tab="password">E-mail +
                        heslo</button><button class="tab" type="button" data-tab="linktoken">Odkaz do e-mailu</button>
                </div>
                <div class="tab-panel active" data-panel="password">
                    <form method="post"><input type="hidden" name="action" value="login_password"><label
                            for="login_email">E-mail</label><input id="login_email" type="email" name="login_email"
                            required><label for="login_password">Heslo</label><input id="login_password" type="password"
                            name="login_password" required><button class="btn" type="submit">Přihlásit se</button>
                    </form>
                </div>
                <div class="tab-panel" data-panel="linktoken">
                    <form method="post"><input type="hidden" name="action" value="request_linktoken"><label
                            for="token_email">E-mail</label><input id="token_email" type="email" name="token_email"
                            required>
                        <p class="inline-note">Tato akce odešle přihlašovací odkaz pomocí funkce <code>mail()</code>.
                            Aby fungovala, musí být na serveru nastavené odesílání pošty.</p><button class="btn"
                            type="submit">Poslat přihlašovací odkaz</button>
                    </form>
                </div>
                <div class="footer-note">Po kliknutí na odkaz z e-mailu showroom zpracuje token i na této stránce, pokud
                    ji otevřeš s parametrem <code><?= showroom_h($token_key) ?></code> v URL.</div>
            </div>
        </div>
    </div>
    <script>
        (() => { 
            const body = document.body, registerModal = document.getElementById('modal-register'), loginModal = document.getElementById('modal-login');
            
            function openModal(name)
            {
                closeModal();
                const modal = name === 'register' ? registerModal : loginModal;
                if (modal) modal.classList.add('open') 
            }
            
            function closeModal()
            {
                document.querySelectorAll('.modal').forEach(m => m.classList.remove('open')) 
            }
            
            document.querySelectorAll('[data-open]').forEach(btn => btn.addEventListener('click', () => openModal(btn.getAttribute('data-open')))); 
            
            document.querySelectorAll('[data-close]').forEach(btn => btn.addEventListener('click', closeModal));
            
            document.querySelectorAll('.modal').forEach(modal => modal.addEventListener('click', e => { 
                    if (e.target === modal) closeModal() 
                }));
            
            document.addEventListener('keydown', e => {
                    if (e.key === 'Escape') closeModal() 
                });
            
            const checkbox = document.getElementById('register_with_password'), passwordWrap = document.getElementById('register-password-wrap');
            
            if (checkbox && passwordWrap) {
                const sync = () => {
                        passwordWrap.hidden = !checkbox.checked;
                        const input = document.getElementById('register_password');
                        if (input) input.required = checkbox.checked
                    };
                
                checkbox.addEventListener('change', sync);
                sync()
            }
            
            const tabs = document.querySelectorAll('.tab'), panels = document.querySelectorAll('.tab-panel');
            
            function activateTab(name) 
            {
                tabs.forEach(tab => tab.classList.toggle('active', tab.dataset.tab === name));
                
                panels.forEach(panel => panel.classList.toggle('active', panel.dataset.panel === name))
            }
            
            tabs.forEach(tab => tab.addEventListener('click', () => activateTab(tab.dataset.tab)));
            
            const openOnLoad = body.dataset.openModal;
            
            if (openOnLoad) {
                openModal(openOnLoad)
            }
        })();
    </script>
</body>

</html>