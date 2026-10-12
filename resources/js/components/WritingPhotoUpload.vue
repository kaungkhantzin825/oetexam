<!--
  components/WritingPhotoUpload.vue
  ---------------------------------------------------------------------------
  Photographed hand-written Writing answers.

  A candidate may write the OET referral letter on paper, photograph it and
  upload the photo instead of typing. The AI reads the handwriting, transcribes
  it and marks it against the six criteria — see
  AiManager::scoreWritingFromImage().

  Why this is its own component: the upload is the only part of the exam that
  talks to the server on its own schedule (the photo must be durable BEFORE
  submit, or a refresh loses the work), and it is the only part that needs a
  camera affordance. Keeping it here leaves QuestionCard a pure presentational
  component.

  Upload contract: POST /attempts/{attempt}/writing-image (multipart)
    → 201 { success, data: { image_path, url, size } }
  Remove contract: DELETE /attempts/{attempt}/writing-image/{questionId}
-->
<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { Camera, ImagePlus, RefreshCw, Trash2, Upload, X } from 'lucide-vue-next';
import { Spinner } from './ui';
import { useTranslation } from '@/lib/i18n';
import { resolveAssetUrl } from '@/lib/format';
import { http } from '@/lib/http';
import { toast } from '@/lib/toast';

const props = defineProps({
    attemptId: { type: Number, required: true },
    questionId: { type: Number, required: true },
    imagePath: { type: String, default: '' },
});

const emit = defineEmits(['update:imagePath']);

const { t } = useTranslation();

const input = ref(null);
const uploading = ref(false);
const removing = ref(false);
const dragging = ref(false);
const error = ref(null);
/** Object URL of a photo being uploaded, shown while the server catches up. */
const localPreview = ref(null);

const hasPhoto = computed(() => Boolean(props.imagePath));
const previewUrl = computed(
    () => localPreview.value || (props.imagePath ? resolveAssetUrl(props.imagePath) : null),
);

/*
 * Phone cameras produce 3–8 MB files. Sending that straight up is slow on a
 * mobile connection and inflates the base64 payload the vision model receives
 * by another third. Downscaling to 1600px on the long edge keeps handwriting
 * perfectly legible while cutting the upload by an order of magnitude.
 */
const MAX_EDGE = 1600;
const JPEG_QUALITY = 0.85;

async function shrink(file) {
    if (!file?.type?.startsWith('image/')) return file;

    try {
        const bitmap = await createImageBitmap(file);
        const scale = Math.min(1, MAX_EDGE / Math.max(bitmap.width, bitmap.height));

        // Already small enough — re-encoding would only lose quality.
        if (scale === 1 && file.size <= 1.5 * 1024 * 1024) {
            bitmap.close?.();
            return file;
        }

        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close?.();

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', JPEG_QUALITY));

        return blob ? new File([blob], 'answer.jpg', { type: 'image/jpeg' }) : file;
    } catch {
        // Any failure here is a convenience problem, not a correctness one —
        // send the original and let the server's size limit decide.
        return file;
    }
}

function releasePreview() {
    if (localPreview.value) {
        URL.revokeObjectURL(localPreview.value);
        localPreview.value = null;
    }
}

async function handleFile(file) {
    if (!file || uploading.value) return;

    error.value = null;
    uploading.value = true;
    releasePreview();
    localPreview.value = URL.createObjectURL(file);

    try {
        const body = new FormData();
        body.append('image', await shrink(file));
        body.append('question_id', String(props.questionId));

        const res = await http.post(`/attempts/${props.attemptId}/writing-image`, body);

        emit('update:imagePath', res.data.image_path);
        releasePreview();
        toast.success(t('exam.photoUploaded'));
    } catch (err) {
        releasePreview();
        error.value = err.friendlyMessage;
        toast.from(err, t('exam.photoUploadFailed'));
    } finally {
        uploading.value = false;
        // Let the same file be re-picked after a failure.
        if (input.value) input.value.value = '';
    }
}

function onPick(event) {
    handleFile(event.target.files?.[0]);
}

function onDrop(event) {
    dragging.value = false;
    handleFile(event.dataTransfer?.files?.[0]);
}

async function removePhoto() {
    if (removing.value) return;

    removing.value = true;
    error.value = null;

    try {
        await http.delete(`/attempts/${props.attemptId}/writing-image/${props.questionId}`);
        releasePreview();
        emit('update:imagePath', '');
        toast.success(t('exam.photoRemoved'));
    } catch (err) {
        error.value = err.friendlyMessage;
        toast.from(err, t('errors.generic'));
    } finally {
        removing.value = false;
    }
}

onBeforeUnmount(releasePreview);
</script>

<template>
    <div>
        <!-- Hidden picker. `capture` opens the camera directly on a phone. -->
        <input
            ref="input"
            type="file"
            class="hidden"
            accept="image/jpeg,image/png,image/webp"
            capture="environment"
            @change="onPick"
        />

        <!-- ---- Photo already uploaded ---- -->
        <div v-if="hasPhoto && !uploading" class="rounded-xl border border-teal-200 bg-teal-50/50 p-3">
            <div class="flex items-start gap-3">
                <a
                    :href="previewUrl"
                    target="_blank"
                    rel="noopener"
                    class="shrink-0 overflow-hidden rounded-lg border border-teal-300 bg-white"
                    :title="t('exam.viewFullPhoto')"
                >
                    <img :src="previewUrl" alt="" class="h-24 w-24 object-cover" />
                </a>

                <div class="min-w-0 flex-1">
                    <p class="inline-flex items-center gap-1.5 text-sm font-semibold text-teal-800">
                        <Camera :size="14" /> {{ t('exam.photoAttached') }}
                    </p>
                    <p class="mt-1 text-xs leading-relaxed text-teal-700">{{ t('exam.photoAttachedHint') }}</p>

                    <div class="mt-2.5 flex flex-wrap gap-2">
                        <button type="button" class="btn-secondary btn-sm gap-1.5" @click="input.click()">
                            <RefreshCw :size="12" /> {{ t('exam.replacePhoto') }}
                        </button>
                        <button
                            type="button"
                            class="btn-ghost btn-sm gap-1.5 text-red-600 hover:bg-red-50"
                            :disabled="removing"
                            @click="removePhoto"
                        >
                            <Spinner v-if="removing" :size="12" />
                            <Trash2 v-else :size="12" />
                            {{ t('exam.removePhoto') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ---- Uploading / previewing ---- -->
        <div v-else-if="uploading" class="rounded-xl border border-primary-200 bg-primary-50/50 p-3">
            <div class="flex items-center gap-3">
                <img v-if="localPreview" :src="localPreview" alt="" class="h-24 w-24 shrink-0 rounded-lg border border-primary-300 object-cover" />
                <div class="min-w-0 flex-1">
                    <p class="inline-flex items-center gap-2 text-sm font-semibold text-primary-800">
                        <Spinner :size="14" /> {{ t('exam.photoUploading') }}
                    </p>
                    <p class="mt-1 text-xs text-primary-700">{{ t('exam.photoUploadingHint') }}</p>
                </div>
            </div>
        </div>

        <!-- ---- Drop area ---- -->
        <div
            v-else
            :class="[
                'cursor-pointer rounded-xl border-2 border-dashed p-6 text-center transition',
                dragging ? 'border-primary-500 bg-primary-50' : 'border-slate-300 bg-slate-50 hover:border-primary-400 hover:bg-primary-50/40',
            ]"
            role="button"
            tabindex="0"
            @click="input.click()"
            @keydown.enter.prevent="input.click()"
            @keydown.space.prevent="input.click()"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-primary-100 text-primary-600">
                <ImagePlus :size="22" />
            </div>

            <p class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-slate-800">
                <Upload :size="14" /> {{ t('exam.uploadPhoto') }}
            </p>
            <p class="mx-auto mt-1.5 max-w-md text-xs leading-relaxed text-slate-500">{{ t('exam.uploadPhotoHint') }}</p>
            <p class="mt-2 text-[11px] font-medium uppercase tracking-wide text-slate-400">{{ t('exam.uploadPhotoFormats') }}</p>
        </div>

        <!-- ---- Inline error ---- -->
        <p v-if="error" class="mt-2 flex items-start gap-1.5 rounded-lg bg-red-50 p-2.5 text-xs leading-relaxed text-red-700">
            <X :size="13" class="mt-0.5 shrink-0" />
            {{ error }}
        </p>
    </div>
</template>
