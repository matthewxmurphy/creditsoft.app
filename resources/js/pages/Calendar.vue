<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import OfficeCalendarWorkspace from '@/components/creditsoft/OfficeCalendarWorkspace.vue';

type BookingLink = {
    name: string;
    url: string;
    channel: string;
};

type VisibleUser = {
    id: number | string;
    name: string;
    role_label: string | null;
};

const props = defineProps<{
    calendar: {
        portal_booking_name: string | null;
        calendar_email: string | null;
        booking_links: BookingLink[];
        scope_label: string;
        scope_summary: string;
        can_view_everyone: boolean;
        visible_users: VisibleUser[];
    };
}>();
</script>

<template>
    <Head title="Office Calendar" />

    <OfficeCalendarWorkspace
        :portal-booking-name="props.calendar.portal_booking_name"
        :calendar-email="props.calendar.calendar_email"
        :booking-links="props.calendar.booking_links"
        :scope-label="props.calendar.scope_label"
        :scope-summary="props.calendar.scope_summary"
        :can-view-everyone="props.calendar.can_view_everyone"
        :visible-users="props.calendar.visible_users"
    />
</template>
