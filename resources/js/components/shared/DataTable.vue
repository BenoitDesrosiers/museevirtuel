<script setup lang="ts">
defineProps<{
    empty?: boolean;
}>();
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <slot />
        </table>
    </div>
</template>
