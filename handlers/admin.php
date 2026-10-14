<?php
// ---------------------------------------------------------------------
// Small local helpers
// ---------------------------------------------------------------------

function abtn(string $text, string $callbackData, ?string $style = null): array
{
    return tg_button($text, $callbackData, null, $style);
}

/** Public https base URL of this bot folder (saved by the installer, or derived from the current request). */
function site_base_url(): string
{
    $saved = get_setting('site.base_url');
    if ($saved !== '') {
        return $saved;
    }
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') {
        return '';
    }
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    $url = 'https://' . $host . $dir;
    set_text('site.base_url', $url);
    return $url;
}

/** Inline button that opens the drag-free row/column layout editor as a Telegram Mini App. */
function admin_layout_webapp_button(): array
{
    return ['text' => '🧩 چیدمان دکمه‌های منو (مینی‌اپ)', 'web_app' => ['url' => site_base_url() . '/webapp/layout.html']];
}

function admin_send_main_menu(int $chatId, ?int $editMessageId = null): void
{
    $rows = [
        [abtn('🖥 پنل‌ها', 'adm:panels'), abtn('📦 پلن‌ها', 'adm:plans')],
        [abtn('🏢 پلن‌های نمایندگی', 'adm:agency_plans')],
        [abtn('📢 کانال‌های عضویت اجباری', 'adm:channels')],
        [abtn('💳 تنظیمات پرداخت', 'adm:payment')],
        [abtn('✉️ ویرایش متن‌ها', 'adm:texts')],
        [admin_layout_webapp_button()],
        [abtn('🔘 ویرایش متن/رنگ/ایموجی دکمه‌های منو', 'adm:gridmenu'), abtn('🔘 دکمه‌های دیگر', 'adm:buttons')],
        [abtn('📣 ارسال همگانی', 'adm:broadcast')],
        [abtn('👤 مدیریت کاربر', 'adm:users')],
        [abtn('⏳ سفارش‌های در انتظار', 'adm:pending')],
    ];
    $markup = tg_inline_markup($rows);
    $menuText = "ربات رایگان vnet ✨\n\n"
        . "این نسخه رایگان و در چنل @vnet_connect قرار دارد ✅\n\n"
        . "خرید و فروش این ربات ممنوع است 🪄";
    if ($editMessageId) {
        tg_edit_message($chatId, $editMessageId, $menuText, $markup);
    } else {
        tg_send_message($chatId, $menuText, $markup);
    }
}

// ---------------------------------------------------------------------
// Callback router
// ---------------------------------------------------------------------

function admin_handle_callback(string $data, int $userId, int $chatId, int $messageId, string $callbackId, ?array $message = null): void
{
    $p = explode(':', $data);

    if ($data === 'adm:back') {
        clear_state($userId);
        admin_send_main_menu($chatId, $messageId);
        tg_answer_callback($callbackId);
        return;
    }

    // ---- panels ----
    if ($data === 'adm:panels') { admin_panels($chatId, $messageId); tg_answer_callback($callbackId); return; }
    if ($data === 'adm:panels:add') {
        set_state($userId, 'admin_panel_name');
        tg_send_message($chatId, 'نام پنل رو بفرست (این اسم به مشتری‌ها نشون داده می‌شه):');
        tg_answer_callback($callbackId);
        return;
    }
    if ($p[0] === 'adm' && $p[1] === 'panels' && $p[2] === 'del') {
        delete_panel((int) $p[3]);
        tg_answer_callback($callbackId, 'حذف شد.');
        admin_panels($chatId, $messageId);
        return;
    }

    // ---- single panel edit (rename / color / emoji / toggle / add admin) ----
    if ($p[0] === 'adm' && $p[1] === 'panel') {
        $action = $p[2];
        $panelId = (int) ($p[3] ?? 0);

        if ($action === 'edit') { admin_panel_edit_menu($panelId, $chatId, $messageId); tg_answer_callback($callbackId); return; }
        if ($action === 'rename') {
            set_state($userId, 'admin_panel_rename', ['panel_id' => $panelId]);
            tg_send_message($chatId, 'نام جدید پنل رو بفرست:');
            tg_answer_callback($callbackId);
            return;
        }
        if ($action === 'colormenu') {
            $rows = [
                [abtn('🔵 آبی', "adm:panel:setcolor:$panelId:primary", 'primary')],
                [abtn('🟢 سبز', "adm:panel:setcolor:$panelId:success", 'success')],
                [abtn('🔴 قرمز', "adm:panel:setcolor:$panelId:danger", 'danger')],
                [abtn('⚪️ پیش‌فرض', "adm:panel:setcolor:$panelId:none")],
                [abtn('🔙 بازگشت', "adm:panel:edit:$panelId")],
            ];
            tg_edit_message($chatId, $messageId, 'رنگ دکمه‌ی این پنل رو انتخاب کن:', tg_inline_markup($rows));
            tg_answer_callback($callbackId);
            return;
        }
        if ($action === 'setcolor') {
            $style = $p[4];
            update_panel_field($panelId, ['button_style' => $style === 'none' ? null : $style]);
            tg_answer_callback($callbackId, '✅ رنگ تغییر کرد.');
            admin_panel_edit_menu($panelId, $chatId, $messageId);
            return;
        }
        if ($action === 'emoji') {
            set_state($userId, 'admin_panel_emoji', ['panel_id' => $panelId]);
            tg_send_message($chatId, "یک پیام بفرست که فقط شامل یک ایموجی پرمیوم باشه، یا «حذف» رو بفرست.\n⚠️ فقط وقتی روی دکمه نمایش داده می‌شه که خود ربات پرمیوم داشته باشه.");
            tg_answer_callback($callbackId);
            return;
        }
        if ($action === 'toggle') {
            $panel = get_panel($panelId);
            update_panel_field($panelId, ['is_active' => $panel['is_active'] ? 0 : 1]);
            tg_answer_callback($callbackId, '✅ به‌روزرسانی شد.');
            admin_panel_edit_menu($panelId, $chatId, $messageId);
            return;
        }
        if ($action === 'addadmin') {
            set_state($userId, 'admin_panel_addadmin', ['panel_id' => $panelId]);
            tg_send_message($chatId, "یوزرنیم و پسورد ادمین جدید رو با این فرمت بفرست:\n<code>یوزرنیم|پسورد</code>\n\n⚠️ این یه لاگین مستقیم به داشبورد پنل می‌سازه، نه ادمین ربات.");
            tg_answer_callback($callbackId);
            return;
        }
    }

    // ---- plans ----
    if ($data === 'adm:plans') { admin_plans($chatId, $messageId); tg_answer_callback($callbackId); return; }
    if ($data === 'adm:plans:add') {
        $panels = list_all_panels();
        if (!$panels) {
            tg_answer_callback($callbackId, 'اول باید یه پنل اضافه کنی.', true);
            return;
        }
        $rows = [];
        foreach ($panels as $panel) {
            $rows[] = [abtn($panel['name'], "adm:plans:add:panel:{$panel['id']}")];
        }
        $rows[] = [abtn('🔙 بازگشت', 'adm:plans')];
        tg_edit_message($chatId, $messageId, 'پلن جدید برای کدوم پنل باشه؟', tg_inline_markup($rows));
        tg_answer_callback($callbackId);
        return;
    }
    if ($p[0] === 'adm' && $p[1] === 'plans' && $p[2] === 'add' && $p[3] === 'panel') {
        set_state($userId, 'admin_plan_name', ['panel_id' => (int) $p[4]]);
        tg_send_message($chatId, 'نام پلن رو بفرست:');
        tg_answer_callback($callbackId);
        return;
    }
    if ($p[0] === 'adm' && $p[1] === 'plans' && $p[2] === 'del') {
        delete_plan((int) $p[3]);
        tg_answer_callback($callbackId, 'حذف شد.');
        admin_plans($chatId, $messageId);
        return;
    }

    // ---- single plan edit (rename / color / emoji / toggle) ----
    if ($p[0] === 'adm' && $p[1] === 'plan') {
        $action = $p[2];
        $planId = (int) ($p[3] ?? 0);

        if ($action === 'edit') { admin_plan_edit_menu($planId, $chatId, $messageId); tg_answer_callback($callbackId); return; }
        if ($action === 'rename') {
            set_state($userId, 'admin_plan_rename', ['plan_id' => $planId]);
            tg_send_message($chatId, 'نام جدید پلن رو بفرست:');
            tg_answer_callback($callbackId);
            return;
        }
        if ($action === 'colormenu') {
            $rows = [
                [abtn('🔵 آبی', "adm:plan:setcolor:$planId:primary", 'primary')],
                [abtn('🟢 سبز', "adm:plan:setcolor:$planId:success", 'success')],
                [abtn('🔴 قرمز', "adm:plan:setcolor:$planId:danger", 'danger')],
                [abtn('⚪️ پیش‌فرض', "adm:plan:setcolor:$planId:none")],
                [abtn('🔙 بازگشت', "adm:plan:edit:$planId")],
            ];
            tg_edit_message($chatId, $messageId, 'رنگ دکمه‌ی این پلن رو انتخاب کن:', tg_inline_markup($rows));
            tg_answer_callback($callbackId);
            return;
        }
        if ($action === 'setcolor') {
            $style = $p[4];
            update_plan_field($planId, ['button_style' => $style === 'none' ? null : $style]);
            tg_answer_callback($callbackId, '✅ رنگ تغییر کرد.');
            admin_plan_edit_menu($planId, $chatId, $messageId);
            return;
        }
        if ($action === 'emoji') {
            set_state($userId, 'admin_plan_emoji', ['plan_id' => $planId]);
            tg_send_message($chatId, "یک پیام بفرست که فقط شامل یک ایموجی پرمیوم باشه، یا «حذف» رو بفرست.\n⚠️ فقط وقتی روی دکمه نمایش داده می‌شه که خود ربات پرمیوم داشته باشه.");
            tg_answer_callback($callbackId);
            return;
        }
        if ($action === 'toggle') {
            $plan = get_plan($planId);
            update_plan_field($planId, ['is_active' => $plan['is_active'] ? 0 : 1]);
            tg_answer_callback($callbackId, '✅ به‌روزرسانی شد.');
            admin_plan_edit_menu($planId, $chatId, $messageId);
            return;
        }
        if ($action === 'testcfg') {
            set_state($userId, 'admin_plan_test_count', ['plan_id' => $planId]);
            tg_send_message($chatId, "هر کاربر در هر دوره چند بار بتونه از این پلن تست بگیره؟\nعدد ۰ بفرست تا تست این پلن کلاً غیرفعال بشه.\n\n(در مرحله‌ی بعد انتخاب می‌کنی این تعداد «هر روز»، «هر هفته»، «هر ماه» یا «فقط یک‌بار» باشه.)");
            tg_answer_callback($callbackId);
            return;
        }
        if ($action === 'testperiod') {
            update_plan_field($planId, ['test_period' => $p[4]]);
            tg_answer_callback($callbackId, '✅ ذخیره شد.');
            admin_plan_edit_menu($planId, $chatId, $messageId);
            return;
        }
        if ($action === 'testonly') {
            $plan = get_plan($planId);
            update_plan_field($planId, ['test_only' => $plan['test_only'] ? 0 : 1]);
            tg_answer_callback($callbackId, '✅ به‌روزرسانی شد.');
            admin_plan_edit_menu($planId, $chatId, $messageId);
            return;
        }
    }

    // ---- agency plans ----
    if ($data === 'adm:agency_plans') { admin_agency_plans($chatId, $messageId); tg_answer_callback($callbackId); return; }
    if ($data === 'adm:agency_plans:add') {
        $panels = list_all_panels();
        if (!$panels) {
            tg_answer_callback($callbackId, 'اول باید یه پنل اضافه کنی.', true);
            return;
        }
        $rows = [];
        foreach ($panels as $panel) {
            $rows[] = [abtn($panel['name'], "adm:agency_plans:add:panel:{$panel['id']}")];
        }
        $rows[] = [abtn('🔙 بازگشت', 'adm:agency_plans')];
        tg_edit_message($chatId, $messageId, 'این پلن نمایندگی رو روی کدوم پنل بسازیم؟', tg_inline_markup($rows));
        tg_answer_callback($callbackId);
        return;
    }
    if ($p[0] === 'adm' && $p[1] === 'agency_plans' && $p[2] === 'add' && $p[3] === 'panel') {
        $panelId = (int) $p[4];
        set_state($userId, 'admin_agency_name', ['panel_id' => $panelId]);
        $hint = '';
        try {
            $panel = get_panel($panelId);
            $roles = pg_list_roles($panel);
            if ($roles) {
                $names = array_map(fn($r) => is_array($r) ? (($r['name'] ?? '?') . ' = ' . ($r['id'] ?? '?')) : $r, $roles);
                $hint = "\n\nنقش‌های موجود روی این پنل: " . implode('، ', $names);
            }
        } catch (Exception $e) {
            // role listing is best-effort — the panel might not support it at all
        }
        tg_send_message($chatId, 'نام پلن نمایندگی رو بفرست:' . $hint);
        tg_answer_callback($callbackId);
        return;
    }
    if ($p[0] === 'adm' && $p[1] === 'agency_plans' && $p[2] === 'del') {
        delete_agency_plan((int) $p[3]);
        tg_answer_callback($callbackId, 'حذف شد.');
        admin_agency_plans($chatId, $messageId);
        return;
    }
    if ($p[0] === 'adm' && $p[1] === 'agencyplan') {
        $action = $p[2];
        $planId = (int) ($p[3] ?? 0);
        if ($action === 'edit') { admin_agency_plan_edit_menu($planId, $chatId, $messageId); tg_answer_callback($callbackId); return; }
        if ($action === 'rename') {
            set_state($userId, 'admin_agencyplan_rename', ['plan_id' => $planId]);
            tg_send_message($chatId, 'نام جدید رو بفرست:');
            tg_answer_callback($callbackId);
            return;
        }
        if ($action === 'gbprice') {
            set_state($userId, 'admin_agencyplan_gbprice', ['plan_id' => $planId]);
            tg_send_message($chatId, 'هر گیگ مصرف چند تومان از موجودی نماینده کم بشه؟ (فقط عدد)');
            tg_answer_callback($callbackId);
            return;
        }
        if ($action === 'hourprice') {
            set_state($userId, 'admin_agencyplan_hourprice', ['plan_id' => $planId]);
            tg_send_message($chatId, 'هر ساعت فعال بودن پنل چند تومان از موجودی نماینده کم بشه؟ (فقط عدد، می‌تونه ۰ باشه)');
            tg_answer_callback($callbackId);
            return;
        }
        if ($action === 'maxusers') {
            set_state($userId, 'admin_agencyplan_maxusers', ['plan_id' => $planId]);
            tg_send_message($chatId, 'حداکثر تعداد کاربر این پلن چقدر باشه؟ (فقط عدد)');
            tg_answer_callback($callbackId);
            return;
        }
        if ($action === 'minbalance') {
            set_state($userId, 'admin_agencyplan_minbalance', ['plan_id' => $planId]);
            tg_send_message($chatId, 'حداقل موجودی کیف پول نمایندگی برای خرید این پلن چقدر باشه؟ (تومان، «0» یعنی بدون حداقل)');
            tg_answer_callback($callbackId);
            return;
        }
        if ($action === 'role') {
            set_state($userId, 'admin_agencyplan_role', ['plan_id' => $planId]);
            tg_send_message($chatId, 'نام رول جدید رو بفرست، یا «حذف» برای برداشتن رول:');
            tg_answer_callback($callbackId);
            return;
        }
        if ($action === 'toggle') {
            $plan = get_agency_plan($planId);
            update_agency_plan_field($planId, ['is_active' => $plan['is_active'] ? 0 : 1]);
            tg_answer_callback($callbackId, '✅ به‌روزرسانی شد.');
            admin_agency_plan_edit_menu($planId, $chatId, $messageId);
            return;
        }
    }

    // ---- per-plan config group selection ----
    if ($p[0] === 'adm' && $p[1] === 'plangroups') {
        $action = $p[2];
        $planId = (int) $p[3];
        if ($action === 'toggle') {
            $groupId = urldecode($p[4]);
            $plan = get_plan($planId);
            $selected = json_decode($plan['group_ids'] ?? '[]', true) ?: [];
            if (in_array($groupId, array_map('strval', $selected), true)) {
                $selected = array_values(array_filter($selected, fn($g) => (string) $g !== $groupId));
            } else {
                $selected[] = $groupId;
            }
            update_plan_field($planId, ['group_ids' => json_encode($selected)]);
            try {
                $groups = pg_list_groups(get_panel((int) get_plan($planId)['panel_id']));
            } catch (Exception $e) {
                $groups = [];
            }
            tg_edit_message($chatId, $messageId, 'این پلن رو با کدوم گروه‌های کانفیگ بسازیم؟ (اگه هیچی انتخاب نکنی، پیش‌فرض خود پنل استفاده می‌شه)', admin_group_selection_markup($planId, $groups));
            tg_answer_callback($callbackId);
            return;
        }
        if ($action === 'done') {
            tg_edit_message($chatId, $messageId, '✅ گروه‌های کانفیگ این پلن ذخیره شد.');
            tg_answer_callback($callbackId);
            return;
        }
    }

    // ---- channels ----
    if ($data === 'adm:channels') { admin_channels($chatId, $messageId); tg_answer_callback($callbackId); return; }
    if ($data === 'adm:channels:add') {
        set_state($userId, 'admin_channel_chatid');
        tg_send_message($chatId, "آیدی عددی یا @یوزرنیم کانال رو بفرست:\n\n⚠️ ربات باید ادمین همون کانال باشه تا بتونه عضویت رو چک کنه.");
        tg_answer_callback($callbackId);
        return;
    }
    if ($p[0] === 'adm' && $p[1] === 'channels' && $p[2] === 'del') {
        delete_channel((int) $p[3]);
        tg_answer_callback($callbackId, 'حذف شد.');
        admin_channels($chatId, $messageId);
        return;
    }

    // ---- payment settings ----
    if ($data === 'adm:payment') { admin_payment($chatId, $messageId); tg_answer_callback($callbackId); return; }
    if ($p[0] === 'adm' && $p[1] === 'payment' && $p[2] === 'edit') {
        $key = $p[3];
        set_state($userId, 'admin_setting_value', ['key' => $key]);
        tg_send_message($chatId, 'مقدار جدید رو بفرست:');
        tg_answer_callback($callbackId);
        return;
    }

    // ---- texts ----
    if ($data === 'adm:texts') { admin_texts($chatId, $messageId); tg_answer_callback($callbackId); return; }
    if ($p[0] === 'adm' && $p[1] === 'texts' && $p[2] === 'edit') {
        $key = implode(':', array_slice($p, 3));
        set_state($userId, 'admin_text_value', ['key' => $key]);
        $current = htmlspecialchars(get_text($key));
        $vars = text_placeholders()[$key] ?? '';
        $varsLine = $vars !== '' ? "\n\nجای‌خالی‌هایی که می‌تونی توی متن بذاری (همون‌طور که هستن بنویس):\n<code>$vars</code>" : '';
        tg_send_message($chatId, '✏️ ' . text_label($key) . "\n\nمتن فعلی:\n\n<code>$current</code>$varsLine\n\nمتن جدید رو بفرست.");
        tg_answer_callback($callbackId);
        return;
    }

    // ---- buttons ----
    if ($data === 'adm:buttons') { admin_buttons($chatId, $messageId); tg_answer_callback($callbackId); return; }
    if ($p[0] === 'adm' && $p[1] === 'buttons') {
        $action = $p[2];
        $key = $p[3] ?? null;

        if ($action === 'edit') { admin_button_edit_menu($key, $chatId, $messageId); tg_answer_callback($callbackId); return; }
        if ($action === 'label') {
            set_state($userId, 'admin_button_label', ['key' => $key]);
            tg_send_message($chatId, 'متن جدید دکمه رو بفرست (می‌تونی ایموجی معمولی هم داخلش بذاری):');
            tg_answer_callback($callbackId);
            return;
        }
        if ($action === 'colormenu') {
            $rows = [
                [abtn('🔵 آبی', "adm:buttons:setcolor:$key:primary", 'primary')],
                [abtn('🟢 سبز', "adm:buttons:setcolor:$key:success", 'success')],
                [abtn('🔴 قرمز', "adm:buttons:setcolor:$key:danger", 'danger')],
                [abtn('⚪️ پیش‌فرض', "adm:buttons:setcolor:$key:none")],
                [abtn('🔙 بازگشت', "adm:buttons:edit:$key")],
            ];
            tg_edit_message($chatId, $messageId, 'رنگ دکمه رو انتخاب کن (پیش‌نمایش خود دکمه‌ها):', tg_inline_markup($rows));
            tg_answer_callback($callbackId);
            return;
        }
        if ($action === 'setcolor') {
            $style = $p[4];
            set_button_field($key, ['style' => $style === 'none' ? null : $style]);
            tg_answer_callback($callbackId, '✅ رنگ تغییر کرد.');
            admin_button_edit_menu($key, $chatId, $messageId);
            return;
        }
        if ($action === 'emoji') {
            set_state($userId, 'admin_button_emoji', ['key' => $key]);
            tg_send_message($chatId, "یک پیام بفرست که فقط شامل یک ایموجی پرمیوم (کاستوم) باشه.\n⚠️ این فقط وقتی روی دکمه نمایش داده می‌شه که خود ربات (یا مالکش) تلگرام پرمیوم داشته باشه.\nبرای حذف ایموجی، کلمه «حذف» رو بفرست.");
            tg_answer_callback($callbackId);
            return;
        }
        if ($action === 'pos') {
            set_state($userId, 'admin_button_position', ['key' => $key]);
            tg_send_message($chatId, 'عدد ترتیب جدید رو بفرست (عدد کوچیک‌تر = بالاتر/جلوتر):');
            tg_answer_callback($callbackId);
            return;
        }
        if ($action === 'toggle') {
            $b = get_button($key);
            set_button_field($key, ['is_visible' => $b['is_visible'] ? 0 : 1]);
            tg_answer_callback($callbackId, '✅ به‌روزرسانی شد.');
            admin_button_edit_menu($key, $chatId, $messageId);
            return;
        }
        if ($action === 'up' || $action === 'down') {
            move_button($key, $action);
            tg_answer_callback($callbackId);
            admin_buttons($chatId, $messageId);
            return;
        }
    }

    // ---- broadcast ----
    if ($data === 'adm:broadcast') {
        $rows = [
            [abtn('✉️ پیام متنی همگانی', 'adm:broadcast:text')],
            [abtn('↪️ فوروارد همگانی', 'adm:broadcast:forward')],
            [abtn('🔙 بازگشت', 'adm:back')],
        ];
        tg_edit_message($chatId, $messageId, 'نوع ارسال همگانی رو انتخاب کن:', tg_inline_markup($rows));
        tg_answer_callback($callbackId);
        return;
    }
    if ($data === 'adm:broadcast:text' || $data === 'adm:broadcast:forward') {
        $mode = $data === 'adm:broadcast:text' ? 'text' : 'forward';
        set_state($userId, 'admin_broadcast_content', ['mode' => $mode]);
        $prompt = $mode === 'text' ? 'پیامی که می‌خوای برای همه ارسال بشه رو بفرست:' : 'پیامی که می‌خوای برای همه فوروارد بشه رو همینجا فوروارد کن:';
        tg_send_message($chatId, $prompt);
        tg_answer_callback($callbackId);
        return;
    }

    // ---- users ----
    if ($data === 'adm:users') {
        set_state($userId, 'admin_user_search');
        tg_send_message($chatId, 'آیدی عددی کاربر رو بفرست (مثلاً از /start که خودش فرستاده یا از فوروارد پیامش):');
        tg_answer_callback($callbackId);
        return;
    }
    if ($p[0] === 'adm' && $p[1] === 'user') {
        $targetTelegramId = (int) $p[2];
        $action = $p[3];
        if ($action === 'bal') {
            $direction = $p[4];
            set_state($userId, 'admin_balance_amount', ['target_telegram_id' => $targetTelegramId, 'direction' => $direction]);
            tg_send_message($chatId, 'مبلغ (تومان) رو بفرست:');
            tg_answer_callback($callbackId);
            return;
        }
        if ($action === 'services') {
            admin_show_user_services($targetTelegramId, $chatId, $messageId);
            tg_answer_callback($callbackId);
            return;
        }
        if ($action === 'delservice') {
            $serviceId = (int) $p[4];
            $service = get_service($serviceId);
            if ($service) {
                $panel = get_panel($service['panel_id']);
                try { pg_remove_user($panel, $service['pasarguard_username']); } catch (Exception $e) { /* already gone on panel, ignore */ }
                deactivate_service($serviceId);
            }
            tg_answer_callback($callbackId, '✅ سرویس حذف شد.');
            return;
        }
    }

    // ---- support reply ----
    if ($p[0] === 'adm' && $p[1] === 'reply') {
        $targetTelegramId = (int) $p[2];
        set_state($userId, 'admin_reply_to_user', ['target_telegram_id' => $targetTelegramId]);
        tg_send_message($chatId, 'پاسخت رو بنویس، همینجا برای کاربر ارسال می‌شه:');
        tg_answer_callback($callbackId);
        return;
    }

    // ---- 4x4 main-menu grid editor ----
    if ($data === 'adm:gridmenu') {
        set_state($userId, 'admin_grid_mode');
        tg_send_message($chatId, 'دکمه‌های منوی اصلی رو می‌بینی — روی هرکدوم بزن تا ویرایشش کنی، یا «➕ خالی» برای جای خالی.', admin_build_grid_keyboard());
        tg_answer_callback($callbackId);
        return;
    }
    if ($p[0] === 'adm' && $p[1] === 'gridbtn') {
        $action = $p[2];
        $key = $p[3];
        if ($action === 'move') {
            $state = get_state($userId);
            set_state($userId, 'admin_grid_mode', ($state['data'] ?? []) + ['moving_key' => $key]);
            tg_send_message($chatId, 'حالا روی جایگاه مقصد (توی کیبورد پایین) بزن — می‌تونی یه دکمه‌ی دیگه یا یه جای خالی رو انتخاب کنی.');
            tg_answer_callback($callbackId);
            return;
        }
        if ($action === 'toggle') {
            $b = get_button($key);
            set_button_field($key, ['is_visible' => $b['is_visible'] ? 0 : 1]);
            tg_answer_callback($callbackId, '✅ به‌روزرسانی شد.');
            tg_send_message($chatId, 'وضعیت دکمه عوض شد.', admin_build_grid_keyboard());
            return;
        }
    }

    // ---- pending orders ----
    if ($data === 'adm:pending') { admin_pending($chatId, $messageId); tg_answer_callback($callbackId); return; }
    if ($p[0] === 'adm' && $p[1] === 'order') {
        $action = $p[2];
        $orderId = (int) $p[3];
        if ($action === 'approve') { admin_approve_order($orderId, $chatId, $messageId, $message); tg_answer_callback($callbackId); return; }
        if ($action === 'reject') { admin_reject_order($orderId, $chatId, $messageId, $message); tg_answer_callback($callbackId); return; }
    }

    tg_answer_callback($callbackId);
}

// ---------------------------------------------------------------------
// Panels / Plans / Channels listings
// ---------------------------------------------------------------------

function admin_panels(int $chatId, int $messageId): void
{
    $rows = [];
    foreach (list_all_panels() as $p) {
        $status = $p['is_active'] ? '🟢' : '🔴';
        $rows[] = [abtn("$status {$p['name']}", "adm:panel:edit:{$p['id']}"), abtn('🗑', "adm:panels:del:{$p['id']}")];
    }
    $rows[] = [abtn('➕ افزودن پنل', 'adm:panels:add')];
    $rows[] = [abtn('🔙 بازگشت', 'adm:back')];
    tg_edit_message($chatId, $messageId, 'پنل‌های ثبت‌شده (روی اسم بزن برای ویرایش):', tg_inline_markup($rows));
}

function admin_panel_edit_menu(int $panelId, int $chatId, int $messageId): void
{
    $panel = get_panel($panelId);
    if (!$panel) {
        return;
    }
    $rows = [
        [abtn('✏️ تغییر نام', "adm:panel:rename:$panelId")],
        [abtn('🎨 تغییر رنگ دکمه', "adm:panel:colormenu:$panelId")],
        [abtn('😀 تغییر ایموجی پرمیوم', "adm:panel:emoji:$panelId")],
        [abtn($panel['is_active'] ? '🔴 غیرفعال کن' : '🟢 فعال کن', "adm:panel:toggle:$panelId")],
        [abtn('👤 افزودن ادمین به این پنل', "adm:panel:addadmin:$panelId")],
        [abtn('🔙 بازگشت', 'adm:panels')],
    ];
    $styleLabel = STYLE_LABELS[$panel['button_style'] ?? ''] ?? $panel['button_style'];
    tg_edit_message($chatId, $messageId, "پنل: <b>{$panel['name']}</b>\nآدرس: {$panel['base_url']}\nرنگ دکمه: $styleLabel\nوضعیت: " . ($panel['is_active'] ? 'فعال' : 'غیرفعال'), tg_inline_markup($rows));
}

function admin_plans(int $chatId, int $messageId): void
{
    $panels = [];
    foreach (list_all_panels() as $p) { $panels[$p['id']] = $p['name']; }
    $rows = [];
    foreach (list_all_plans() as $plan) {
        $panelName = $panels[$plan['panel_id']] ?? '?';
        $label = "{$plan['name']} ($panelName) - " . number_format($plan['price_toman']) . 'ت';
        $rows[] = [abtn($label, "adm:plan:edit:{$plan['id']}"), abtn('🗑', "adm:plans:del:{$plan['id']}")];
    }
    $rows[] = [abtn('➕ افزودن پلن', 'adm:plans:add')];
    $rows[] = [abtn('🔙 بازگشت', 'adm:back')];
    tg_edit_message($chatId, $messageId, 'پلن‌های ثبت‌شده (روی اسم بزن برای ویرایش):', tg_inline_markup($rows));
}

function admin_plan_edit_menu(int $planId, int $chatId, int $messageId): void
{
    $plan = get_plan($planId);
    if (!$plan) {
        return;
    }
    $rows = [
        [abtn('✏️ تغییر نام', "adm:plan:rename:$planId")],
        [abtn('🎨 تغییر رنگ دکمه', "adm:plan:colormenu:$planId")],
        [abtn('😀 تغییر ایموجی پرمیوم', "adm:plan:emoji:$planId")],
        [abtn('🧪 تنظیم سرویس تست (تعداد و دوره)', "adm:plan:testcfg:$planId")],
        [abtn($plan['test_only'] ? '🧪 فقط تست (برای خرید نمایش داده نشه) ✅' : '🧪 فقط تست؟ (الان برای خرید هم نمایش داده می‌شه)', "adm:plan:testonly:$planId")],
        [abtn($plan['is_active'] ? '🔴 غیرفعال کن' : '🟢 فعال کن', "adm:plan:toggle:$planId")],
        [abtn('🔙 بازگشت', 'adm:plans')],
    ];
    $styleLabel = STYLE_LABELS[$plan['button_style'] ?? ''] ?? $plan['button_style'];
    $testLine = (int) $plan['test_max_count'] > 0 ? "هر کاربر {$plan['test_max_count']} بار — " . test_period_label($plan['test_period'] ?? 'once') . ($plan['test_only'] ? ' (فقط تست، برای فروش نمایش داده نمی‌شه)' : '') : 'تست غیرفعاله';
    tg_edit_message($chatId, $messageId, "پلن: <b>{$plan['name']}</b>\nحجم: {$plan['data_limit_gb']}GB | {$plan['duration_days']} روز\nقیمت: " . number_format($plan['price_toman']) . " تومان\nرنگ دکمه: $styleLabel\nتست: $testLine\nوضعیت: " . ($plan['is_active'] ? 'فعال' : 'غیرفعال'), tg_inline_markup($rows));
}

function admin_channels(int $chatId, int $messageId): void
{
    $rows = [];
    foreach (list_all_channels() as $c) {
        $rows[] = [abtn($c['title'], "adm:channels:noop:{$c['id']}"), abtn('🗑', "adm:channels:del:{$c['id']}")];
    }
    $rows[] = [abtn('➕ افزودن کانال', 'adm:channels:add')];
    $rows[] = [abtn('🔙 بازگشت', 'adm:back')];
    tg_edit_message($chatId, $messageId, 'کانال‌های عضویت اجباری:', tg_inline_markup($rows));
}

// ---------------------------------------------------------------------
// Agency plans
// ---------------------------------------------------------------------

function admin_agency_plans(int $chatId, int $messageId): void
{
    $panels = [];
    foreach (list_all_panels() as $p) { $panels[$p['id']] = $p['name']; }
    $rows = [];
    foreach (list_all_agency_plans() as $plan) {
        $panelName = $panels[$plan['panel_id']] ?? '?';
        $label = "{$plan['name']} ($panelName)";
        $rows[] = [abtn($label, "adm:agencyplan:edit:{$plan['id']}"), abtn('🗑', "adm:agency_plans:del:{$plan['id']}")];
    }
    $rows[] = [abtn('➕ افزودن پلن نمایندگی', 'adm:agency_plans:add')];
    $rows[] = [abtn('🔙 بازگشت', 'adm:back')];
    tg_edit_message($chatId, $messageId, 'پلن‌های نمایندگی:', tg_inline_markup($rows));
}

function admin_agency_plan_edit_menu(int $planId, int $chatId, int $messageId): void
{
    $plan = get_agency_plan($planId);
    if (!$plan) {
        return;
    }
    $rows = [
        [abtn('✏️ تغییر نام', "adm:agencyplan:rename:$planId")],
        [abtn('💰 قیمت هر گیگ', "adm:agencyplan:gbprice:$planId")],
        [abtn('⏱ قیمت هر ساعت', "adm:agencyplan:hourprice:$planId")],
        [abtn('👥 حداکثر کاربر', "adm:agencyplan:maxusers:$planId")],
        [abtn('🔒 حداقل موجودی برای خرید', "adm:agencyplan:minbalance:$planId")],
        [abtn('🎭 تغییر رول', "adm:agencyplan:role:$planId")],
        [abtn($plan['is_active'] ? '🔴 غیرفعال کن' : '🟢 فعال کن', "adm:agencyplan:toggle:$planId")],
        [abtn('🔙 بازگشت', 'adm:agency_plans')],
    ];
    $text = "پلن نمایندگی: <b>{$plan['name']}</b>\n"
        . "رول: " . ($plan['role'] ?: '—') . "\n"
        . 'قیمت هر گیگ: ' . number_format($plan['per_gb_price']) . " تومان\n"
        . 'قیمت هر ساعت: ' . number_format($plan['per_hour_price']) . " تومان\n"
        . "حداکثر کاربر: {$plan['max_users']}\n"
        . 'حداقل موجودی برای خرید: ' . number_format($plan['min_balance_toman'] ?? 0) . " تومان\n"
        . 'وضعیت: ' . ($plan['is_active'] ? 'فعال' : 'غیرفعال');
    tg_edit_message($chatId, $messageId, $text, tg_inline_markup($rows));
}

// ---------------------------------------------------------------------
// Config groups for a plan (best-effort — see pg_list_groups() caveat)
// ---------------------------------------------------------------------

function admin_offer_group_selection(int $planId, int $panelId, int $chatId): void
{
    try {
        $panel = get_panel($panelId);
        $groups = pg_list_groups($panel);
    } catch (Exception $e) {
        return; // panel doesn't support/expose this — new users just get its default groups
    }
    if (!$groups) {
        return;
    }
    tg_send_message($chatId, 'این پلن رو با کدوم گروه‌های کانفیگ بسازیم؟ (اگه هیچی انتخاب نکنی، پیش‌فرض خود پنل استفاده می‌شه)', admin_group_selection_markup($planId, $groups));
}

function admin_group_selection_markup(int $planId, array $groups): array
{
    $plan = get_plan($planId);
    $selected = json_decode($plan['group_ids'] ?? '[]', true) ?: [];
    $rows = [];
    foreach ($groups as $g) {
        $id = is_array($g) ? ($g['id'] ?? $g['name'] ?? null) : $g;
        $name = is_array($g) ? ($g['name'] ?? (string) $id) : (string) $g;
        if ($id === null) {
            continue;
        }
        $mark = in_array((string) $id, array_map('strval', $selected), true) ? '✅ ' : '⬜️ ';
        $rows[] = [abtn($mark . $name, "adm:plangroups:toggle:$planId:" . urlencode((string) $id))];
    }
    $rows[] = [abtn('✅ تمام، ذخیره شد', "adm:plangroups:done:$planId")];
    return tg_inline_markup($rows);
}

// ---------------------------------------------------------------------
// Payment settings
// ---------------------------------------------------------------------

const PAYMENT_KEYS = [
    'payment.card_number' => 'شماره کارت (سرویس معمولی)',
    'payment.card_holder' => 'نام صاحب کارت (سرویس معمولی)',
    'payment.usdt_wallet_trc20' => 'کیف پول تتر (TRC20، سرویس معمولی)',
    'payment.ton_wallet' => 'کیف پول تون (سرویس معمولی)',
    'payment.agency_card_number' => 'شماره کارت (نمایندگی)',
    'payment.agency_card_holder' => 'نام صاحب کارت (نمایندگی)',
    'payment.agency_ton_wallet' => 'کیف پول تون (نمایندگی)',
    'payment.ton_to_toman_rate' => 'نرخ ۱ تون‌کوین به تومان',
    'agency.login_url' => 'لینک ورود به پنل نمایندگی‌ها',
];

function admin_payment(int $chatId, int $messageId): void
{
    $lines = ["تنظیمات پرداخت فعلی:\n"];
    $rows = [];
    foreach (PAYMENT_KEYS as $key => $label) {
        $lines[] = "• $label: <code>" . get_setting($key) . '</code>';
        $rows[] = [abtn("✏️ $label", "adm:payment:edit:$key")];
    }
    $rows[] = [abtn('🔙 بازگشت', 'adm:back')];
    tg_edit_message($chatId, $messageId, implode("\n", $lines), tg_inline_markup($rows));
}

// ---------------------------------------------------------------------
// Texts
// ---------------------------------------------------------------------

function admin_texts(int $chatId, int $messageId): void
{
    $rows = [];
    foreach (text_labels() as $key => $label) {
        $rows[] = [abtn($label, "adm:texts:edit:$key")];
    }
    $rows[] = [abtn('🔙 بازگشت', 'adm:back')];
    tg_edit_message($chatId, $messageId, 'کدوم متن رو می‌خوای ویرایش کنی؟', tg_inline_markup($rows));
}

// ---------------------------------------------------------------------
// Buttons
// ---------------------------------------------------------------------

const STYLE_LABELS = ['primary' => '🔵 آبی', 'success' => '🟢 سبز', 'danger' => '🔴 قرمز', '' => '⚪️ پیش‌فرض (خاکستری)'];

function admin_buttons(int $chatId, int $messageId): void
{
    $rows = [];
    $byParent = [];
    foreach (list_all_buttons() as $b) {
        if ($b['parent'] === 'main_menu') {
            continue; // edited via the 4x4 grid instead — see admin_grid_menu()
        }
        $byParent[$b['parent']][] = $b;
    }
    foreach ($byParent as $parent => $buttons) {
        foreach ($buttons as $i => $b) {
            $visibleIcon = $b['is_visible'] ? '👁' : '🚫';
            $row = [abtn("$visibleIcon {$b['label']} (" . (button_parent_labels()[$parent] ?? $parent) . ")", "adm:buttons:edit:{$b['button_key']}")];
            if ($i > 0) {
                $row[] = abtn('⬆️', "adm:buttons:up:{$b['button_key']}");
            }
            if ($i < count($buttons) - 1) {
                $row[] = abtn('⬇️', "adm:buttons:down:{$b['button_key']}");
            }
            $rows[] = $row;
        }
    }
    $rows[] = [abtn('🔙 بازگشت', 'adm:back')];
    tg_edit_message($chatId, $messageId, 'کدوم دکمه رو می‌خوای تغییر بدی؟ (با ⬆️⬇️ می‌تونی جاش رو عوض کنی)', tg_inline_markup($rows));
}

function admin_button_edit_menu(string $key, int $chatId, int $messageId): void
{
    $b = get_button($key);
    $rows = [
        [abtn('✏️ تغییر متن', "adm:buttons:label:$key")],
        [abtn('🎨 تغییر رنگ', "adm:buttons:colormenu:$key")],
        [abtn('😀 تغییر ایموجی پرمیوم', "adm:buttons:emoji:$key")],
        [abtn('↕️ تغییر ترتیب', "adm:buttons:pos:$key")],
        [abtn($b['is_visible'] ? '🚫 مخفی کن' : '👁 نمایش بده', "adm:buttons:toggle:$key")],
        [abtn('🔙 بازگشت', 'adm:buttons')],
    ];
    $styleLabel = STYLE_LABELS[$b['style'] ?? ''] ?? $b['style'];
    $text = "دکمه: <b>{$b['label']}</b>\nگروه: {$b['parent']}\nرنگ فعلی: $styleLabel\nترتیب: {$b['position']}\nوضعیت: " . ($b['is_visible'] ? 'نمایان' : 'مخفی');
    tg_edit_message($chatId, $messageId, $text, tg_inline_markup($rows));
}

// ---------------------------------------------------------------------
// User management
// ---------------------------------------------------------------------

function admin_show_user_card(int $telegramId, int $chatId): void
{
    $customer = get_customer_by_telegram_id($telegramId);
    if (!$customer) {
        tg_send_message($chatId, 'کاربری با این آیدی پیدا نشد (باید حداقل یک بار /start زده باشه).');
        return;
    }
    $services = list_customer_services($customer['id']);
    $rows = [
        [abtn('➕ افزودن موجودی', "adm:user:$telegramId:bal:add"), abtn('➖ کاهش موجودی', "adm:user:$telegramId:bal:sub")],
        [abtn('🗑 حذف یکی از سرویس‌ها', "adm:user:$telegramId:services")],
        [abtn('🔙 بازگشت', 'adm:back')],
    ];
    tg_send_message($chatId, "👤 کاربر <code>$telegramId</code>\nنام: {$customer['full_name']}\nموجودی کیف پول: " . number_format($customer['wallet_balance_toman']) . " تومان\nتعداد سرویس‌ها: " . count($services), tg_inline_markup($rows));
}

function admin_show_user_services(int $telegramId, int $chatId, int $messageId): void
{
    $customer = get_customer_by_telegram_id($telegramId);
    $services = $customer ? list_customer_services($customer['id']) : [];
    $rows = [];
    foreach ($services as $s) {
        $rows[] = [abtn("🗑 #{$s['id']} {$s['pasarguard_username']}", "adm:user:$telegramId:delservice:{$s['id']}")];
    }
    $rows[] = [abtn('🔙 بازگشت', 'adm:back')];
    tg_edit_message($chatId, $messageId, 'سرویس‌های کاربر:', tg_inline_markup($rows));
}

// ---------------------------------------------------------------------
// Pending orders
// ---------------------------------------------------------------------

function admin_pending(int $chatId, int $messageId): void
{
    $orders = list_pending_card_orders();
    if (!$orders) {
        tg_edit_message($chatId, $messageId, 'سفارش در انتظار تاییدی نیست.', tg_inline_markup([[abtn('🔙 بازگشت', 'adm:back')]]));
        return;
    }
    foreach ($orders as $order) {
        $rows = [[abtn('✅ تایید', "adm:order:approve:{$order['id']}"), abtn('❌ رد', "adm:order:reject:{$order['id']}")]];
        $text = "سفارش #{$order['id']} | نوع: {$order['order_type']} | مبلغ: " . number_format($order['amount_toman'] ?? 0) . " تومان | مشتری: {$order['customer_id']}";
        if ($order['receipt_file_id']) {
            tg_send_photo($chatId, $order['receipt_file_id'], $text, tg_inline_markup($rows));
        } else {
            tg_send_message($chatId, $text . "\n(هنوز رسیدی ارسال نشده)", tg_inline_markup($rows));
        }
    }
}

function admin_approve_order(int $orderId, int $chatId, int $messageId, ?array $message = null): void
{
    $order = get_order($orderId);
    if (!$order) {
        return;
    }
    if ($order['order_type'] === 'agency_topup') {
        fulfill_agency_topup($orderId);
    } else {
        mark_order_paid($orderId);
        fulfill_order($orderId);
    }
    admin_append_to_order_message($chatId, $messageId, $message, "\n\n✅ تایید و تحویل داده شد.");
}

function admin_reject_order(int $orderId, int $chatId, int $messageId, ?array $message = null): void
{
    $order = get_order($orderId);
    if (!$order) {
        return;
    }
    mark_order_rejected($orderId);
    $customer = get_customer_by_id($order['customer_id']);
    if ($customer) {
        tg_send_message((int) $customer['telegram_id'], get_text('order.rejected_customer', ['order_id' => $orderId]));
    }
    admin_append_to_order_message($chatId, $messageId, $message, "\n\n❌ رد شد.");
}

function admin_append_to_order_message(int $chatId, int $messageId, ?array $message, string $suffix): void
{
    if ($message !== null && isset($message['caption'])) {
        tg_edit_caption($chatId, $messageId, $message['caption'] . $suffix);
        return;
    }
    if ($message !== null && isset($message['text'])) {
        tg_edit_message($chatId, $messageId, $message['text'] . $suffix);
        return;
    }
    // Fallback if we somehow don't have the original content
    tg_send_message($chatId, 'سفارش به‌روزرسانی شد.' . $suffix);
}

function admin_build_grid_keyboard(): array
{
    $bySlot = [];
    $maxRow = 3;
    $maxCol = 3;
    foreach (list_buttons_by_parent('main_menu') as $b) {
        $pos = (int) $b['position'];
        $bySlot[$pos] = $b;
        if ($pos < 900) {
            $maxRow = max($maxRow, intdiv($pos, MAIN_MENU_ROW_STEP));
            $maxCol = max($maxCol, $pos % MAIN_MENU_ROW_STEP);
        }
    }
    $rows = [];
    for ($row = 0; $row <= min($maxRow, 9); $row++) {
        $cells = [];
        for ($col = 0; $col <= min($maxCol, 4); $col++) {
            $slot = $row * MAIN_MENU_ROW_STEP + $col;
            if (isset($bySlot[$slot])) {
                $b = $bySlot[$slot];
                $label = ($b['is_visible'] ? '' : '🚫 ') . $b['label'];
            } else {
                $label = "➕ خالی #$slot";
            }
            $cells[] = tg_button($label);
        }
        $rows[] = $cells;
    }
    $rows[] = [tg_button('🔙 بازگشت به پنل مدیریت')];
    return tg_reply_markup($rows);
}

/** Finds which main_menu button (if any) currently has this exact label text, hidden ones included. */
function admin_find_grid_button_by_label(string $text): ?array
{
    foreach (list_buttons_by_parent('main_menu') as $b) {
        $label = ($b['is_visible'] ? '' : '🚫 ') . $b['label'];
        if ($label === $text) {
            return $b;
        }
    }
    return null;
}

function admin_send_grid_button_card(string $key, int $chatId): void
{
    $b = get_button($key);
    $rows = [
        [abtn('✏️ تغییر متن', "adm:buttons:label:$key")],
        [abtn('🎨 تغییر رنگ', "adm:buttons:colormenu:$key")],
        [abtn('😀 تغییر ایموجی پرمیوم', "adm:buttons:emoji:$key")],
        [abtn('↕️ تغییر جایگاه', "adm:gridbtn:move:$key")],
        [abtn($b['is_visible'] ? '🚫 مخفی کن' : '👁 نمایش بده', "adm:gridbtn:toggle:$key")],
    ];
    $styleLabel = STYLE_LABELS[$b['style'] ?? ''] ?? $b['style'];
    tg_send_message($chatId, "دکمه: <b>{$b['label']}</b>\nرنگ: $styleLabel\nوضعیت: " . ($b['is_visible'] ? 'نمایان' : 'مخفی'), tg_inline_markup($rows));
}

// ---------------------------------------------------------------------
// Stateful text messages
// ---------------------------------------------------------------------

function admin_handle_state_message(string $state, array $data, array $message, int $userId, int $chatId): void
{
    $text = $message['text'] ?? null;

    // ---- panel creation: name -> url -> admin username -> admin password ----
    if ($state === 'admin_panel_name') {
        $name = trim($text ?? '');
        if ($name === '') { tg_send_message($chatId, 'یه نام بفرست.'); return; }
        set_state($userId, 'admin_panel_url', ['name' => $name]);
        tg_send_message($chatId, 'آدرس پنل رو بفرست (مثال: https://panel.example.com):');
        return;
    }
    if ($state === 'admin_panel_url') {
        $url = trim($text ?? '');
        if ($url === '' || !str_starts_with($url, 'http')) { tg_send_message($chatId, 'آدرس معتبر نیست، با http:// یا https:// بفرست.'); return; }
        set_state($userId, 'admin_panel_username', $data + ['url' => $url]);
        tg_send_message($chatId, 'یوزرنیم ادمین پنل رو بفرست:');
        return;
    }
    if ($state === 'admin_panel_username') {
        $username = trim($text ?? '');
        if ($username === '') { tg_send_message($chatId, 'یوزرنیم خالیه، دوباره بفرست.'); return; }
        set_state($userId, 'admin_panel_password', $data + ['username' => $username]);
        tg_send_message($chatId, 'پسورد ادمین پنل رو بفرست:');
        return;
    }
    if ($state === 'admin_panel_password') {
        $password = trim($text ?? '');
        if ($password === '') { tg_send_message($chatId, 'پسورد خالیه، دوباره بفرست.'); return; }
        create_panel($data['name'], $data['url'], $data['username'], $password);
        clear_state($userId);
        tg_send_message($chatId, "✅ پنل «{$data['name']}» اضافه شد.");
        return;
    }

    // ---- plan creation: name -> data limit -> duration -> toman price -> usdt price ----
    if ($state === 'admin_plan_name') {
        $name = trim($text ?? '');
        if ($name === '') { tg_send_message($chatId, 'یه نام بفرست.'); return; }
        set_state($userId, 'admin_plan_gb', $data + ['name' => $name]);
        tg_send_message($chatId, 'حجم پلن به گیگابایت رو بفرست (فقط عدد، مثلاً 30):');
        return;
    }
    if ($state === 'admin_plan_gb') {
        if (!is_numeric($text ?? '')) { tg_send_message($chatId, 'فقط عدد بفرست.'); return; }
        set_state($userId, 'admin_plan_days', $data + ['gb' => (float) $text]);
        tg_send_message($chatId, 'مدت پلن به روز رو بفرست (فقط عدد، مثلاً 30):');
        return;
    }
    if ($state === 'admin_plan_days') {
        if (!ctype_digit($text ?? '')) { tg_send_message($chatId, 'فقط عدد بفرست.'); return; }
        set_state($userId, 'admin_plan_toman', $data + ['days' => (int) $text]);
        tg_send_message($chatId, 'قیمت پلن به تومان رو بفرست (فقط عدد):');
        return;
    }
    if ($state === 'admin_plan_toman') {
        if (!ctype_digit($text ?? '')) { tg_send_message($chatId, 'فقط عدد بفرست.'); return; }
        set_state($userId, 'admin_plan_usdt', $data + ['toman' => (int) $text]);
        tg_send_message($chatId, 'قیمت پلن به تتر رو بفرست (فقط عدد، می‌تونه اعشاری باشه، مثلاً 5.5):');
        return;
    }
    if ($state === 'admin_plan_usdt') {
        if (!is_numeric($text ?? '')) { tg_send_message($chatId, 'فقط عدد بفرست.'); return; }
        $planId = create_plan((int) $data['panel_id'], $data['name'], (float) $data['gb'], (int) $data['days'], (int) $data['toman'], (float) $text);
        clear_state($userId);
        tg_send_message($chatId, "✅ پلن «{$data['name']}» اضافه شد.");
        admin_offer_group_selection($planId, (int) $data['panel_id'], $chatId);
        return;
    }

    // ---- channel: chat id -> title -> invite link ----
    if ($state === 'admin_channel_chatid') {
        $chatIdent = trim($text ?? '');
        if ($chatIdent === '') { tg_send_message($chatId, 'یه آیدی/یوزرنیم بفرست.'); return; }
        set_state($userId, 'admin_channel_title', ['chat_id' => $chatIdent]);
        tg_send_message($chatId, 'یه عنوان برای کانال بفرست (همین به کاربر نشون داده می‌شه):');
        return;
    }
    if ($state === 'admin_channel_title') {
        $title = trim($text ?? '');
        if ($title === '') { tg_send_message($chatId, 'عنوان خالیه، دوباره بفرست.'); return; }
        set_state($userId, 'admin_channel_link', $data + ['title' => $title]);
        tg_send_message($chatId, 'لینک عضویت کانال رو بفرست (مثال: https://t.me/channel):');
        return;
    }
    if ($state === 'admin_channel_link') {
        $link = trim($text ?? '');
        if ($link === '') { tg_send_message($chatId, 'لینک خالیه، دوباره بفرست.'); return; }
        create_channel($data['chat_id'], $data['title'], $link);
        clear_state($userId);
        tg_send_message($chatId, "✅ کانال «{$data['title']}» اضافه شد.");
        return;
    }

    if ($state === 'admin_plan_test_count') {
        if (!ctype_digit($text ?? '')) { tg_send_message($chatId, 'فقط عدد بفرست.'); return; }
        $planId = (int) $data['plan_id'];
        update_plan_field($planId, ['test_max_count' => (int) $text]);
        clear_state($userId);
        if ((int) $text <= 0) {
            tg_send_message($chatId, '✅ تست این پلن غیرفعال شد.');
            return;
        }
        $rows = [
            [abtn('📅 هر روز', "adm:plan:testperiod:$planId:daily"), abtn('🗓 هر هفته', "adm:plan:testperiod:$planId:weekly")],
            [abtn('📆 هر ماه', "adm:plan:testperiod:$planId:monthly"), abtn('1️⃣ فقط یک‌بار (کلاً)', "adm:plan:testperiod:$planId:once")],
        ];
        tg_send_message($chatId, 'این تعداد تست در چه دوره‌ای برای هر کاربر تکرار بشه؟', tg_inline_markup($rows));
        return;
    }

    // ---- agency plan creation: name -> role -> per-gb -> per-hour -> max users ----
    if ($state === 'admin_agency_name') {
        $name = trim($text ?? '');
        if ($name === '') { tg_send_message($chatId, 'یه نام بفرست.'); return; }
        set_state($userId, 'admin_agency_role', $data + ['name' => $name]);
        tg_send_message($chatId, 'شماره (ID) یا نام نقش این پلن روی پنل چیه؟ اگه نمی‌خوای انتخاب کنی، یه خط تیره (-) بفرست تا اولین نقش پنل استفاده بشه.');
        return;
    }
    if ($state === 'admin_agency_role') {
        $role = trim($text ?? '');
        set_state($userId, 'admin_agency_gbprice', $data + ['role' => ($role === '-' || $role === '') ? null : $role]);
        tg_send_message($chatId, 'هر گیگ مصرف چند تومان از موجودی نماینده کم بشه؟ (فقط عدد)');
        return;
    }
    if ($state === 'admin_agency_gbprice') {
        if (!ctype_digit($text ?? '')) { tg_send_message($chatId, 'فقط عدد بفرست.'); return; }
        set_state($userId, 'admin_agency_hourprice', $data + ['gb_price' => (int) $text]);
        tg_send_message($chatId, 'هر ساعت فعال بودن پنل چند تومان کم بشه؟ (فقط عدد، می‌تونه ۰ باشه)');
        return;
    }
    if ($state === 'admin_agency_hourprice') {
        if (!ctype_digit($text ?? '')) { tg_send_message($chatId, 'فقط عدد بفرست.'); return; }
        set_state($userId, 'admin_agency_maxusers', $data + ['hour_price' => (int) $text]);
        tg_send_message($chatId, 'حداکثر تعداد کاربر این پلن چقدر باشه؟ (فقط عدد)');
        return;
    }
    if ($state === 'admin_agency_maxusers') {
        if (!ctype_digit($text ?? '')) { tg_send_message($chatId, 'فقط عدد بفرست.'); return; }
        set_state($userId, 'admin_agency_minbalance', $data + ['max_users' => (int) $text]);
        tg_send_message($chatId, 'حداقل موجودی کیف پول نمایندگی برای خرید این پلن چقدر باشه؟ (تومان؛ اگه موجودی کمتر از این باشه، نمی‌تونه پلن رو بخره. «0» یعنی بدون حداقل)');
        return;
    }
    if ($state === 'admin_agency_minbalance') {
        if (!ctype_digit($text ?? '')) { tg_send_message($chatId, 'فقط عدد بفرست.'); return; }
        create_agency_plan((int) $data['panel_id'], $data['name'], $data['role'], (int) $data['gb_price'], (int) $data['hour_price'], (int) $data['max_users'], (int) $text);
        clear_state($userId);
        tg_send_message($chatId, "✅ پلن نمایندگی «{$data['name']}» اضافه شد.");
        return;
    }
    if ($state === 'admin_agencyplan_minbalance') {
        if (!ctype_digit($text ?? '')) { tg_send_message($chatId, 'فقط عدد بفرست.'); return; }
        update_agency_plan_field((int) $data['plan_id'], ['min_balance_toman' => (int) $text]);
        clear_state($userId);
        tg_send_message($chatId, '✅ حداقل موجودی به‌روزرسانی شد.');
        return;
    }

    // ---- agency plan single-field edits ----
    if ($state === 'admin_agencyplan_rename') {
        $name = trim($text ?? '');
        if ($name === '') { tg_send_message($chatId, 'یه نام بفرست.'); return; }
        update_agency_plan_field((int) $data['plan_id'], ['name' => $name]);
        clear_state($userId);
        tg_send_message($chatId, '✅ نام به‌روزرسانی شد.');
        return;
    }
    if ($state === 'admin_agencyplan_gbprice') {
        if (!ctype_digit($text ?? '')) { tg_send_message($chatId, 'فقط عدد بفرست.'); return; }
        update_agency_plan_field((int) $data['plan_id'], ['per_gb_price' => (int) $text]);
        clear_state($userId);
        tg_send_message($chatId, '✅ قیمت به‌روزرسانی شد.');
        return;
    }
    if ($state === 'admin_agencyplan_hourprice') {
        if (!ctype_digit($text ?? '')) { tg_send_message($chatId, 'فقط عدد بفرست.'); return; }
        update_agency_plan_field((int) $data['plan_id'], ['per_hour_price' => (int) $text]);
        clear_state($userId);
        tg_send_message($chatId, '✅ قیمت به‌روزرسانی شد.');
        return;
    }
    if ($state === 'admin_agencyplan_maxusers') {
        if (!ctype_digit($text ?? '')) { tg_send_message($chatId, 'فقط عدد بفرست.'); return; }
        update_agency_plan_field((int) $data['plan_id'], ['max_users' => (int) $text]);
        clear_state($userId);
        tg_send_message($chatId, '✅ به‌روزرسانی شد.');
        return;
    }
    if ($state === 'admin_agencyplan_role') {
        $role = trim($text ?? '');
        update_agency_plan_field((int) $data['plan_id'], ['role' => $role === 'حذف' ? null : $role]);
        clear_state($userId);
        tg_send_message($chatId, '✅ رول به‌روزرسانی شد.');
        return;
    }

    if ($state === 'admin_grid_mode') {
        if (trim($text ?? '') === '🔙 بازگشت به پنل مدیریت') {
            clear_state($userId);
            tg_api('sendMessage', ['chat_id' => $chatId, 'text' => 'بازگشت به پنل مدیریت.', 'reply_markup' => ['remove_keyboard' => true]]);
            admin_send_main_menu($chatId);
            return;
        }

        $movingKey = $data['moving_key'] ?? null;
        $targetSlot = null;
        if (preg_match('/#(\d+)$/', $text ?? '', $m)) {
            $targetSlot = (int) $m[1]; // tapped an empty cell
        }
        $tappedButton = admin_find_grid_button_by_label($text ?? '');
        if ($tappedButton !== null) {
            $targetSlot = (int) $tappedButton['position'];
        }

        if ($movingKey !== null) {
            if ($targetSlot === null) {
                tg_send_message($chatId, 'یکی از جایگاه‌های کیبورد پایین رو انتخاب کن.');
                return;
            }
            move_button_to_slot($movingKey, $targetSlot);
            set_state($userId, 'admin_grid_mode'); // clear moving_key
            tg_send_message($chatId, '✅ جابه‌جا شد.', admin_build_grid_keyboard());
            return;
        }

        if ($tappedButton !== null) {
            admin_send_grid_button_card($tappedButton['button_key'], $chatId);
            return;
        }

        tg_send_message($chatId, 'این جایگاه خالیه. برای قرار دادن یه دکمه اینجا، اول روی یه دکمه‌ی دیگه بزن و «↕️ تغییر جایگاه» رو انتخاب کن.');
        return;
    }

    if ($state === 'admin_panel_rename') {
        $name = trim($text ?? '');
        if ($name === '') {
            tg_send_message($chatId, 'یه نام معتبر بفرست.');
            return;
        }
        update_panel_field((int) $data['panel_id'], ['name' => $name]);
        clear_state($userId);
        tg_send_message($chatId, '✅ نام پنل تغییر کرد.');
        return;
    }

    if ($state === 'admin_panel_emoji') {
        if (trim($text ?? '') === 'حذف') {
            update_panel_field((int) $data['panel_id'], ['button_emoji_id' => null]);
            clear_state($userId);
            tg_send_message($chatId, '✅ ایموجی حذف شد.');
            return;
        }
        $customEmojiId = null;
        foreach ($message['entities'] ?? [] as $entity) {
            if (($entity['type'] ?? '') === 'custom_emoji') {
                $customEmojiId = $entity['custom_emoji_id'];
                break;
            }
        }
        if (!$customEmojiId) {
            tg_send_message($chatId, 'ایموجی پرمیوم پیدا نشد. دوباره امتحان کن، یا «حذف» رو بفرست.');
            return;
        }
        update_panel_field((int) $data['panel_id'], ['button_emoji_id' => $customEmojiId]);
        clear_state($userId);
        tg_send_message($chatId, '✅ ایموجی به‌روزرسانی شد.');
        return;
    }

    if ($state === 'admin_panel_addadmin') {
        $parts = explode('|', $text ?? '');
        if (count($parts) !== 2) {
            tg_send_message($chatId, 'فرمت درست نیست. مثال: newadmin|StrongPass@12Ab (پسورد: حداقل ۲ حرف بزرگ، حرف کوچک، عدد و یک نماد مثل @)');
            return;
        }
        [$username, $password] = array_map('trim', $parts);
        $panel = get_panel((int) $data['panel_id']);
        clear_state($userId);
        try {
            pg_create_admin($panel, $username, $password, false);
            tg_send_message($chatId, "✅ ادمین «{$username}» روی پنل «{$panel['name']}» ساخته شد.");
        } catch (Exception $e) {
            tg_send_message($chatId, '❌ ساخت ادمین ناموفق بود: ' . $e->getMessage());
        }
        return;
    }

    if ($state === 'admin_plan_rename') {
        $name = trim($text ?? '');
        if ($name === '') {
            tg_send_message($chatId, 'یه نام معتبر بفرست.');
            return;
        }
        update_plan_field((int) $data['plan_id'], ['name' => $name]);
        clear_state($userId);
        tg_send_message($chatId, '✅ نام پلن تغییر کرد.');
        return;
    }

    if ($state === 'admin_plan_emoji') {
        if (trim($text ?? '') === 'حذف') {
            update_plan_field((int) $data['plan_id'], ['button_emoji_id' => null]);
            clear_state($userId);
            tg_send_message($chatId, '✅ ایموجی حذف شد.');
            return;
        }
        $customEmojiId = null;
        foreach ($message['entities'] ?? [] as $entity) {
            if (($entity['type'] ?? '') === 'custom_emoji') {
                $customEmojiId = $entity['custom_emoji_id'];
                break;
            }
        }
        if (!$customEmojiId) {
            tg_send_message($chatId, 'ایموجی پرمیوم پیدا نشد. دوباره امتحان کن، یا «حذف» رو بفرست.');
            return;
        }
        update_plan_field((int) $data['plan_id'], ['button_emoji_id' => $customEmojiId]);
        clear_state($userId);
        tg_send_message($chatId, '✅ ایموجی به‌روزرسانی شد.');
        return;
    }

    if ($state === 'admin_setting_value') {
        set_text($data['key'], trim($text ?? ''));
        clear_state($userId);
        tg_send_message($chatId, '✅ ذخیره شد.');
        return;
    }

    if ($state === 'admin_text_value') {
        set_text($data['key'], $text ?? '');
        clear_state($userId);
        tg_send_message($chatId, '✅ متن به‌روزرسانی شد.');
        return;
    }

    if ($state === 'admin_button_label') {
        set_button_field($data['key'], ['label' => trim($text ?? '')]);
        clear_state($userId);
        tg_send_message($chatId, '✅ متن دکمه به‌روزرسانی شد.');
        return;
    }

    if ($state === 'admin_button_emoji') {
        if (trim($text ?? '') === 'حذف') {
            set_button_field($data['key'], ['emoji_custom_id' => null]);
            clear_state($userId);
            tg_send_message($chatId, '✅ ایموجی حذف شد.');
            return;
        }
        $customEmojiId = null;
        foreach ($message['entities'] ?? [] as $entity) {
            if (($entity['type'] ?? '') === 'custom_emoji') {
                $customEmojiId = $entity['custom_emoji_id'];
                break;
            }
        }
        if (!$customEmojiId) {
            tg_send_message($chatId, 'ایموجی پرمیوم پیدا نشد. یه پیام فقط شامل یک ایموجی پرمیوم بفرست، یا «حذف» رو بفرست.');
            return;
        }
        set_button_field($data['key'], ['emoji_custom_id' => $customEmojiId]);
        clear_state($userId);
        tg_send_message($chatId, '✅ ایموجی دکمه به‌روزرسانی شد.');
        return;
    }

    if ($state === 'admin_button_position') {
        if (!ctype_digit(ltrim($text ?? '', '-'))) {
            tg_send_message($chatId, 'لطفاً فقط عدد بفرست.');
            return;
        }
        set_button_field($data['key'], ['position' => (int) $text]);
        clear_state($userId);
        tg_send_message($chatId, '✅ ترتیب به‌روزرسانی شد.');
        return;
    }

    if ($state === 'admin_broadcast_content') {
        $mode = $data['mode'];
        clear_state($userId);
        $targets = list_all_customer_telegram_ids();
        tg_send_message($chatId, '⏳ در حال ارسال به ' . count($targets) . ' کاربر...');
        $sent = 0;
        $failed = 0;
        foreach ($targets as $tid) {
            $result = $mode === 'forward'
                ? tg_forward_message((int) $tid, $chatId, $message['message_id'])
                : tg_copy_message((int) $tid, $chatId, $message['message_id']);
            if ($result['ok'] ?? false) { $sent++; } else { $failed++; }
            usleep(50000); // stay well under Telegram's flood limits
        }
        tg_send_message($chatId, "✅ ارسال شد به $sent کاربر. ناموفق: $failed");
        return;
    }

    if ($state === 'admin_user_search') {
        if (!$text || !ctype_digit($text)) {
            tg_send_message($chatId, 'لطفاً فقط آیدی عددی بفرست.');
            return;
        }
        clear_state($userId);
        admin_show_user_card((int) $text, $chatId);
        return;
    }

    if ($state === 'admin_balance_amount') {
        if (!$text || !ctype_digit($text)) {
            tg_send_message($chatId, 'لطفاً فقط عدد بفرست.');
            return;
        }
        $targetTelegramId = (int) $data['target_telegram_id'];
        $direction = $data['direction'];
        $amount = (int) $text;
        $delta = $direction === 'add' ? $amount : -$amount;
        $customer = get_customer_by_telegram_id($targetTelegramId);
        if (!$customer) {
            clear_state($userId);
            tg_send_message($chatId, 'کاربر پیدا نشد.');
            return;
        }
        adjust_wallet_balance($customer['id'], $delta);
        clear_state($userId);
        tg_send_message($chatId, '✅ موجودی به‌روزرسانی شد.');
        $sign = $delta > 0 ? '+' : '';
        tg_send_message($targetTelegramId, "💰 موجودی کیف پول شما {$sign}" . number_format($delta) . ' تومان تغییر کرد.');
        return;
    }

    if ($state === 'admin_reply_to_user') {
        $targetTelegramId = (int) $data['target_telegram_id'];
        clear_state($userId);
        if ($text === null && isset($message['photo'])) {
            $photos = $message['photo'];
            $fileId = end($photos)['file_id'];
            tg_send_photo($targetTelegramId, $fileId, '📩 پاسخ پشتیبانی' . (($message['caption'] ?? '') ? ("\n\n" . $message['caption']) : ''));
        } else {
            tg_send_message($targetTelegramId, "📩 پاسخ پشتیبانی:\n\n" . ($text ?? ''));
        }
        tg_send_message($chatId, '✅ پاسخ ارسال شد.');
        return;
    }
}
