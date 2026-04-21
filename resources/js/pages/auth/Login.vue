<script setup lang="ts">
import { computed } from 'vue';
import { Form, Head, usePage } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Log in to your account',
        description: 'Enter your email and password below to log in',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();

const page = usePage();
const loginAccounts = computed(
    () =>
        (
            page.props.creditsoft as
                | {
                      access?: {
                          login_accounts?: Array<{
                              label: string;
                              email: string;
                              password: string;
                              readonly: boolean;
                              description?: string | null;
                          }>;
                      };
                  }
                | undefined
        )?.access?.login_accounts ?? [],
);
</script>

<template>
    <Head title="Sign In" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <Form
        v-bind="store()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Password</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                        :tabindex="5"
                    >
                        Forgot password?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Password"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <input
                        id="remember"
                        checked
                        class="size-4 shrink-0 rounded-[4px] border border-input text-primary shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        name="remember"
                        :tabindex="3"
                        type="checkbox"
                        value="1"
                    />
                    <span>Remember me</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Log in
            </Button>
        </div>

        <div
            class="text-center text-sm text-muted-foreground"
            v-if="canRegister"
        >
            Don't have an account?
            <TextLink :href="register()" :tabindex="5">Sign up</TextLink>
        </div>
    </Form>

    <div
        v-if="loginAccounts.length"
        class="mt-8 rounded-[28px] border border-stone-200 bg-stone-50/85 p-5"
    >
        <div class="space-y-1">
            <p
                class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
            >
                Demo access
            </p>
            <p class="text-sm leading-6 text-stone-600">
                Only the read-only walkthrough accounts are shown here.
            </p>
        </div>

        <div class="mt-4 grid gap-3">
            <div
                v-for="account in loginAccounts"
                :key="account.email"
                class="rounded-[22px] border border-stone-200 bg-white px-4 py-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-stone-950">
                            {{ account.label }}
                        </p>
                        <p
                            v-if="account.description"
                            class="mt-1 text-xs leading-5 text-stone-500"
                        >
                            {{ account.description }}
                        </p>
                    </div>
                    <span
                        class="rounded-full px-2.5 py-1 text-[10px] font-semibold tracking-[0.22em] uppercase"
                        :class="
                            account.readonly
                                ? 'bg-amber-100 text-amber-800'
                                : 'bg-stone-950 text-white'
                        "
                    >
                        {{ account.readonly ? 'View only' : 'Write access' }}
                    </span>
                </div>

                <div
                    class="mt-3 grid gap-2 text-sm text-stone-700 sm:grid-cols-[1fr_auto]"
                >
                    <div class="font-medium break-all text-stone-900">
                        {{ account.email }}
                    </div>
                    <div class="font-mono text-stone-500">
                        {{ account.password }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
