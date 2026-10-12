<!--
  Pages/Admin/AiLogs.vue
  ---------------------------------------------------------------------------
  AI usage: the last 100 calls with their latency and outcome, plus a per-feature
  summary. `model` is provider-prefixed (`gemini:…` / `mistral:…`), which makes
  it obvious when the Mistral fallback is doing the work.
-->
<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Bot, CheckCircle2, XCircle } from 'lucide-vue-next';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { EmptyState, ProgressBar, StatCard } from '@/components/ui';
import { useTranslation } from '@/lib/i18n';
import { formatDate } from '@/lib/format';

const props = defineProps({
    logs: { type: Array, default: () => [] },
    summary: { type: Array, default: () => [] },
});

const { t } = useTranslation();

const totals = computed(() => {
    const calls = props.summary.reduce((n, r) => n + Number(r.calls || 0), 0);
    const failures = props.summary.reduce((n, r) => n + Number(r.failures || 0), 0);
    const latencies = props.summary.map((r) => Number(r.avg_ms || 0)).filter(Boolean);
    const avgMs = latencies.length ? Math.round(latencies.reduce((a, b) => a + b, 0) / latencies.length) : 0;
    return { calls, failures, avgMs, successRate: calls ? ((calls - failures) / calls) * 100 : 100 };
});

/** Split "gemini:gemini-3.5-flash" into provider + model for display. */
function splitModel(value) {
    const raw = String(value || '—');
    const i = raw.indexOf(':');
    return i === -1 ? { provider: '—', model: raw } : { provider: raw.slice(0, i), model: raw.slice(i + 1) };
}

const PROVIDER_TONE = {
    gemini: 'badge-blue',
    mistral: 'badge-purple',
};
</script>

<template>
    <Head :title="t('admin.aiLogs')" />

    <AdminLayout>
        <header>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ t('admin.aiLogs') }}</h1>
            <p class="mt-1 text-sm text-slate-600">
                {{ t('admin.aiLogs') }} — {{ totals.calls }} {{ t('common.all').toLowerCase() }}
            </p>
        </header>

        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            <StatCard :icon="Bot" :label="t('admin.aiLogs')" :value="totals.calls" tone="blue" />
            <StatCard
                :icon="CheckCircle2"
                :label="t('result.accuracy')"
                :value="totals.successRate.toFixed(0) + '%'"
                tone="teal"
            />
            <StatCard :icon="XCircle" :label="t('common.status')" :value="totals.avgMs + ' ms'" tone="amber" />
        </div>

        <!-- ---- Per-feature summary ---- -->
        <section class="card mt-6 overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-base font-semibold text-slate-900">{{ t('dashboard.bySection') }}</h2>
            </div>

            <div v-if="summary.length" class="divide-y divide-slate-100">
                <div v-for="row in summary" :key="row.feature" class="px-5 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="inline-flex items-center gap-2">
                            <span class="badge-blue">{{ row.feature }}</span>
                            <span class="text-xs text-slate-500">{{ row.calls }} calls</span>
                        </div>
                        <span class="text-xs text-slate-500">
                            {{ row.avg_ms ?? 0 }} ms ·
                            <span :class="Number(row.failures) > 0 ? 'font-semibold text-red-600' : ''">
                                {{ row.failures ?? 0 }} {{ t('result.incorrect').toLowerCase() }}
                            </span>
                        </span>
                    </div>
                    <ProgressBar
                        class="mt-2"
                        :value="Number(row.calls) - Number(row.failures || 0)"
                        :max="Number(row.calls) || 1"
                        tone="teal"
                    />
                </div>
            </div>

            <div v-else class="p-5"><EmptyState :icon="Bot" :title="t('common.noData')" /></div>
        </section>

        <!-- ---- Recent calls ---- -->
        <section class="card mt-6 overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-base font-semibold text-slate-900">{{ t('dashboard.recentAttempts') }}</h2>
            </div>

            <div v-if="logs.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-left font-semibold">#</th>
                            <th class="px-5 py-3 text-left font-semibold">{{ t('admin.aiLogs') }}</th>
                            <th class="px-5 py-3 text-left font-semibold">Model</th>
                            <th class="px-5 py-3 text-right font-semibold">ms</th>
                            <th class="px-5 py-3 text-left font-semibold">{{ t('common.status') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="log in logs" :key="log.id" class="transition hover:bg-slate-50/70">
                            <td class="px-5 py-3 text-xs tabular-nums text-slate-400">{{ log.id }}</td>
                            <td class="px-5 py-3">
                                <p class="font-medium text-slate-700">{{ log.feature }}</p>
                                <p class="text-[11px] text-slate-400">
                                    {{ log.user_id ? `user #${log.user_id}` : 'system' }}
                                </p>
                            </td>
                            <td class="px-5 py-3">
                                <span :class="PROVIDER_TONE[splitModel(log.model).provider] || 'badge-slate'">
                                    {{ splitModel(log.model).provider }}
                                </span>
                                <p class="mt-1 font-mono text-[11px] text-slate-500">{{ splitModel(log.model).model }}</p>
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums text-slate-600">{{ log.latency_ms ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <span :class="log.success ? 'badge-green' : 'badge-red'">
                                    {{ log.success ? t('result.correct') : t('result.incorrect') }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right text-xs text-slate-500">
                                {{ formatDate(log.created_at, true) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="p-5"><EmptyState :icon="Bot" :title="t('common.noData')" /></div>
        </section>
    </AdminLayout>
</template>
