<script setup lang="ts">
type EmailProvider =
    | 'custom_smtp'
    | 'microsoft_365'
    | 'google_workspace'
    | 'amazon_ses'
    | 'sendgrid'
    | 'mailgun'
    | 'zoho_mail'
    | 'postmark'
    | 'brevo'
    | 'smtp_com';

const props = withDefaults(
    defineProps<{
        provider: EmailProvider;
        large?: boolean;
        compact?: boolean;
        iconOnly?: boolean;
        hero?: boolean;
    }>(),
    {
        large: false,
        compact: false,
        iconOnly: false,
        hero: false,
    },
);

const logoFor = (provider: EmailProvider) => {
    if (provider === 'microsoft_365') {
        return '/branding/email-providers/microsoft-365.svg';
    }

    if (provider === 'google_workspace') {
        return '/branding/email-providers/google-workspace.svg';
    }

    if (provider === 'amazon_ses') {
        return '/branding/email-providers/amazon-ses.svg';
    }

    if (provider === 'sendgrid') {
        return '/branding/email-providers/sendgrid.svg';
    }

    if (provider === 'mailgun') {
        return '/branding/email-providers/mailgun.svg';
    }

    if (provider === 'zoho_mail') {
        return '/branding/email-providers/zoho-mail.svg';
    }

    if (provider === 'postmark') {
        return '/branding/email-providers/postmark.svg';
    }

    if (provider === 'brevo') {
        return '/branding/email-providers/brevo.svg';
    }

    if (provider === 'smtp_com') {
        return '/branding/email-providers/smtp-com.svg';
    }

    return '/branding/email-providers/smtp.svg';
};

const labelFor = (provider: EmailProvider) => {
    if (provider === 'microsoft_365') {
        return 'Microsoft 365';
    }

    if (provider === 'google_workspace') {
        return 'Google Workspace';
    }

    if (provider === 'amazon_ses') {
        return 'Amazon SES';
    }

    if (provider === 'sendgrid') {
        return 'SendGrid';
    }

    if (provider === 'mailgun') {
        return 'Mailgun';
    }

    if (provider === 'zoho_mail') {
        return 'Zoho Mail';
    }

    if (provider === 'postmark') {
        return 'Postmark';
    }

    if (provider === 'brevo') {
        return 'Brevo';
    }

    if (provider === 'smtp_com') {
        return 'SMTP.com';
    }

    return 'SMTP';
};

const svgClass = () => {
    if (props.compact) {
        return 'h-[18px] w-[18px]';
    }

    if (props.hero) {
        return 'h-full w-full max-h-[90px] max-w-[170px]';
    }

    return props.large ? 'h-8 w-8' : 'h-6 w-6';
};

const imageClass = () => {
    if (props.compact) {
        return 'h-[18px]';
    }

    if (props.hero) {
        return 'h-full w-full max-h-[90px] max-w-[170px]';
    }

    return props.large ? 'h-8' : 'h-6';
};
</script>

<template>
    <svg
        v-if="props.iconOnly && props.provider === 'custom_smtp'"
        viewBox="0 0 24 24"
        xmlns="http://www.w3.org/2000/svg"
        role="img"
        :aria-label="labelFor(props.provider)"
        class="shrink-0"
        :class="svgClass()"
        fill="none"
    >
        <path
            d="M4.75 7.5A2.25 2.25 0 0 1 7 5.25h10a2.25 2.25 0 0 1 2.25 2.25v9A2.25 2.25 0 0 1 17 18.75H7a2.25 2.25 0 0 1-2.25-2.25v-9Z"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
        <path
            d="m5.25 7.1 5.65 5.06a1.65 1.65 0 0 0 2.2 0l5.65-5.06"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
    </svg>
    <img
        v-else
        :src="logoFor(props.provider)"
        :alt="labelFor(props.provider)"
        class="w-auto shrink-0 object-contain"
        :class="imageClass()"
    />
</template>
