export const MATERIAL_ICON_CATEGORIES = [
    {
        id: 'sante',
        label: 'Santé & soins',
        icons: [
            'medical_services', 'local_hospital', 'health_and_safety', 'vaccines', 'medication',
            'emergency', 'stethoscope', 'cardiology', 'pulmonology', 'dentistry', 'ophthalmology',
            'bloodtype', 'healing', 'symptoms', 'ecg_heart', 'monitor_heart', 'clinical_notes',
            'medical_information', 'pill', 'vital_signs',
        ],
    },
    {
        id: 'personnes',
        label: 'Personnes & familles',
        icons: [
            'groups', 'family_restroom', 'person', 'people', 'child_care', 'elderly',
            'diversity_3', 'wc', 'boy', 'girl', 'man', 'woman', 'group_add', 'person_add',
            'supervisor_account', 'diversity_1', 'handshake',
        ],
    },
    {
        id: 'institution',
        label: 'Institution & défense',
        icons: [
            'military_tech', 'shield', 'security', 'gavel', 'account_balance', 'domain',
            'apartment', 'badge', 'verified', 'workspace_premium', 'flag', 'public',
            'corporate_fare', 'assured_workload', 'policy', 'balance',
        ],
    },
    {
        id: 'stats',
        label: 'Statistiques & chiffres',
        icons: [
            'monitoring', 'analytics', 'bar_chart', 'pie_chart', 'trending_up', 'trending_down',
            'percent', 'calculate', 'functions', 'show_chart', 'leaderboard', 'insights',
            'query_stats', 'ssid_chart', 'stacked_line_chart', 'data_usage',
        ],
    },
    {
        id: 'finance',
        label: 'Finance & cotisations',
        icons: [
            'payments', 'account_balance_wallet', 'savings', 'paid', 'credit_card',
            'receipt_long', 'request_quote', 'currency_exchange', 'attach_money',
            'price_check', 'account_tree', 'savings',
        ],
    },
    {
        id: 'communication',
        label: 'Communication',
        icons: [
            'campaign', 'notifications', 'mail', 'call', 'support_agent', 'forum',
            'chat', 'info', 'announcement', 'contact_support', 'mark_email_read',
            'sms', 'wifi_calling', 'record_voice_over',
        ],
    },
    {
        id: 'lieux',
        label: 'Lieux & accès',
        icons: [
            'location_on', 'map', 'home', 'place', 'explore', 'near_me', 'directions',
            'local_hospital', 'local_pharmacy', 'local_post_office', 'pin_drop', 'route',
        ],
    },
    {
        id: 'temps',
        label: 'Temps & historique',
        icons: [
            'history', 'schedule', 'event', 'calendar_month', 'today', 'update',
            'hourglass_top', 'timelapse', 'date_range', 'event_available',
        ],
    },
    {
        id: 'documents',
        label: 'Documents & dossiers',
        icons: [
            'description', 'folder_open', 'article', 'library_books', 'assignment',
            'task', 'inventory', 'fact_check', 'content_paste', 'upload_file',
            'download', 'print', 'contract',
        ],
    },
    {
        id: 'actions',
        label: 'Actions & statut',
        icons: [
            'check_circle', 'verified_user', 'thumb_up', 'star', 'favorite',
            'bolt', 'rocket_launch', 'lightbulb', 'auto_awesome', 'workspace_premium',
            'lock', 'lock_open', 'visibility', 'help', 'warning', 'error',
        ],
    },
];

export const EMOJI_CATEGORIES = [
    {
        id: 'sante-emoji',
        label: 'Santé',
        icons: ['🏥', '💊', '🩺', '❤️', '🩹', '💉', '🧬', '🦷', '👁️', '🚑', '⚕️', '🧑‍⚕️'],
    },
    {
        id: 'personnes-emoji',
        label: 'Personnes',
        icons: ['👨‍👩‍👧‍👦', '👨‍👩‍👦', '👶', '🧒', '👨', '👩', '🧑', '👴', '👵', '🤝', '👥', '🫂'],
    },
    {
        id: 'institution-emoji',
        label: 'Institution',
        icons: ['🎖️', '🛡️', '🏛️', '🏢', '📋', '🇧🇫', '🏳️', '⚖️', '📜', '🔐', '✅', '⭐'],
    },
    {
        id: 'stats-emoji',
        label: 'Chiffres & stats',
        icons: ['📊', '📈', '📉', '💯', '🔢', '📐', '🧮', '💹', '📆', '🗓️', '⏱️', '📍'],
    },
    {
        id: 'finance-emoji',
        label: 'Finance',
        icons: ['💰', '💳', '🪙', '💵', '💶', '🧾', '🏦', '📑', '💼', '🤑', '📉', '💸'],
    },
    {
        id: 'communication-emoji',
        label: 'Communication',
        icons: ['📢', '📣', '📧', '📞', '💬', '📩', '🔔', 'ℹ️', '❗', '❓', '📱', '🗣️'],
    },
    {
        id: 'symboles-emoji',
        label: 'Symboles',
        icons: ['✨', '🎯', '🚀', '💡', '🔥', '👍', '🎉', '🏆', '🌟', '♻️', '🔗', '➕'],
    },
];

export function isEmojiIcon(value) {
    if (!value) {
        return false;
    }

    return /[^\u0000-\u007F]/.test(value);
}

export function allMaterialIcons() {
    return MATERIAL_ICON_CATEGORIES.flatMap((category) => category.icons);
}

export function allEmojis() {
    return EMOJI_CATEGORIES.flatMap((category) => category.icons);
}
