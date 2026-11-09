<script setup lang="ts">
import {onMounted, ref} from 'vue'

const csrfToken = ref('')

/** The backend injects the token into every served page; the local dev server has none, so the button stays hidden there. */
onMounted(() => {
    csrfToken.value = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
})
</script>

<template>
    <form v-if="csrfToken" class="sign-out" method="post" action="/docs/logout">
        <input type="hidden" name="_token" :value="csrfToken">
        <button type="submit">Sign out</button>
    </form>
</template>

<style scoped>
.sign-out {
    display: flex;
    align-items: center;
    margin-inline-start: 12px;
}

.sign-out button {
    font-size: 14px;
    font-weight: 500;
    color: var(--vp-c-text-2);
    transition: color 0.25s;
}

.sign-out button:hover {
    color: var(--vp-c-brand-1);
}
</style>
