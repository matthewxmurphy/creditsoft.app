(function () {
    "use strict";

    var FORM_SELECTOR = "form[data-creditsoft-document-scanner]";
    var FILE_SELECTOR = "input[type=\"file\"][name=\"document_file\"], input[type=\"file\"][data-creditsoft-document-file]";
    var enhancedForms = new WeakSet();

    function ready(callback) {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", callback, { once: true });
            return;
        }

        callback();
    }

    function textFromForm(form, fileInput) {
        var parts = [];
        var title = form.querySelector("[name=\"title\"], [data-creditsoft-document-title]");
        var category = form.querySelector("[name=\"category\"], [data-creditsoft-document-category]");
        var selected = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0].name : "";

        [title, category].forEach(function (field) {
            if (!field) {
                return;
            }

            parts.push(field.value || field.textContent || field.getAttribute("aria-label") || "");
        });

        parts.push(form.dataset.creditsoftDocumentType || "");
        parts.push(form.dataset.creditsoftDocumentName || "");
        parts.push(selected);

        return parts.join(" ").toLowerCase();
    }

    function isCardLike(label) {
        return /driver|license|photo id|identity|identification|passport|social security|ssn|w2|w-2/.test(label);
    }

    function ensureQualityInput(form) {
        var input = form.querySelector("input[name=\"document_quality\"]");

        if (!input) {
            input = document.createElement("input");
            input.type = "hidden";
            input.name = "document_quality";
            form.appendChild(input);
        }

        return input;
    }

    function createPanel(cardLike) {
        var panel = document.createElement("section");
        panel.className = "cs-document-scanner";
        panel.setAttribute("aria-live", "polite");
        panel.innerHTML = [
            "<div class=\"cs-document-scanner__header\">",
            "  <div>",
            "    <p class=\"cs-document-scanner__title\">Clear document upload</p>",
            "    <p class=\"cs-document-scanner__copy\">Use a bright, flat surface and make the document fill the frame before uploading.</p>",
            "  </div>",
            "  <button class=\"cs-document-scanner__button cs-document-scanner__button--primary\" type=\"button\" data-cs-scan>Scan with camera</button>",
            "</div>",
            "<ul class=\"cs-document-scanner__tips\">",
            "  <li>" + (cardLike ? "Turn the phone sideways for IDs and cards." : "Keep the full page flat and inside the photo.") + "</li>",
            "  <li>Move close enough that names and numbers are readable.</li>",
            "  <li>Avoid glare, shadows, fingers, and busy backgrounds.</li>",
            "</ul>",
            "<div class=\"cs-document-scanner__preview\" data-cs-preview></div>",
            "<div class=\"cs-document-scanner__review\" data-cs-review>",
            "  <div class=\"cs-document-scanner__status\">",
            "    <span class=\"cs-document-scanner__badge\" data-cs-badge>Waiting for file</span>",
            "    <span class=\"cs-document-scanner__score\" data-cs-score></span>",
            "  </div>",
            "  <ul class=\"cs-document-scanner__warnings\" data-cs-warnings></ul>",
            "  <div class=\"cs-document-scanner__actions\">",
            "    <button class=\"cs-document-scanner__button\" type=\"button\" data-cs-retake>Retake or choose another file</button>",
            "    <button class=\"cs-document-scanner__button cs-document-scanner__button--primary cs-document-scanner__hidden\" type=\"button\" data-cs-use-anyway>Use this file anyway</button>",
            "  </div>",
            "</div>"
        ].join("");

        return panel;
    }

    function insertPanel(fileInput, panel) {
        var anchor = fileInput.closest("[data-creditsoft-document-upload-field]") || fileInput.closest("label") || fileInput;
        anchor.insertAdjacentElement("afterend", panel);
    }

    function displayPreview(state, element) {
        state.preview.textContent = "";
        state.preview.appendChild(element);

        if (state.cardLike) {
            var guide = document.createElement("div");
            guide.className = "cs-document-scanner__guide";
            state.preview.appendChild(guide);
        }

        state.preview.dataset.visible = "true";
    }

    function setReview(state, result) {
        state.result = result;
        state.confirmed = result.status === "accepted";
        state.qualityInput.value = JSON.stringify(result);

        state.review.dataset.visible = "true";
        state.review.dataset.status = result.status;
        state.badge.textContent = result.status === "accepted" ? "Ready to upload" : "Retake recommended";
        state.score.textContent = typeof result.score === "number" ? "Quality " + result.score + "/100" : "";
        state.warnings.textContent = "";

        if (!result.warnings.length) {
            var clean = document.createElement("li");
            clean.textContent = "Looks usable. Staff can still review it after upload.";
            state.warnings.appendChild(clean);
        } else {
            result.warnings.forEach(function (warning) {
                var item = document.createElement("li");
                item.textContent = warning.message;
                state.warnings.appendChild(item);
            });
        }

        state.useAnyway.classList.toggle("cs-document-scanner__hidden", result.status === "accepted");
    }

    function warning(code, message) {
        return { code: code, message: message };
    }

    function canCaptureToFileInput() {
        return typeof DataTransfer !== "undefined" && typeof File !== "undefined";
    }

    function analyzeImage(img, file, cardLike) {
        var width = img.naturalWidth || img.videoWidth || img.width || 0;
        var height = img.naturalHeight || img.videoHeight || img.height || 0;
        var ratio = height > 0 ? width / height : null;
        var warnings = [];
        var score = 100;

        if (cardLike && height > width) {
            warnings.push(warning("turn_phone_sideways", "Turn the phone sideways and retake it so the card fills a wide frame."));
            score -= 22;
        }

        if (width < 1400 || height < 700) {
            warnings.push(warning("move_closer", "Move closer. The image may be too small for names and numbers to print clearly."));
            score -= 18;
        }

        if (cardLike && ratio !== null && (ratio < 1.25 || ratio > 2.25)) {
            warnings.push(warning("align_card_with_guide", "Line the document up with the guide and crop out extra background."));
            score -= 15;
        }

        var sample = sampleImage(img, width, height);

        if (sample.brightness < 0.26) {
            warnings.push(warning("too_dark", "Use more light. This photo looks dark."));
            score -= 12;
        } else if (sample.brightness > 0.9) {
            warnings.push(warning("too_bright", "Reduce glare or flash. Parts of this photo may be washed out."));
            score -= 12;
        }

        if (sample.sharpness < 0.035 && width > 0 && height > 0) {
            warnings.push(warning("possible_blur", "Hold the phone still and retake if the text is blurry."));
            score -= 14;
        }

        score = Math.max(0, Math.min(100, Math.round(score)));

        return {
            status: score < 82 || warnings.length ? "retake_recommended" : "accepted",
            score: score,
            warnings: warnings,
            dimensions: {
                width: width,
                height: height
            },
            orientation: width > height ? "landscape" : height > width ? "portrait" : "square",
            document_frame_ratio: ratio === null ? null : Math.round(ratio * 1000) / 1000,
            reviewed_at: new Date().toISOString()
        };
    }

    function sampleImage(img, width, height) {
        var canvas = document.createElement("canvas");
        var sampleWidth = 160;
        var sampleHeight = Math.max(1, Math.round(sampleWidth * (height || sampleWidth) / (width || sampleWidth)));
        var context = canvas.getContext("2d", { willReadFrequently: true });
        var brightness = 0;
        var sharpness = 0;
        var pixels;
        var count;
        var x;
        var y;

        canvas.width = sampleWidth;
        canvas.height = sampleHeight;

        if (!context) {
            return { brightness: 0.5, sharpness: 0.1 };
        }

        context.drawImage(img, 0, 0, sampleWidth, sampleHeight);
        pixels = context.getImageData(0, 0, sampleWidth, sampleHeight).data;
        count = sampleWidth * sampleHeight;

        for (var i = 0; i < pixels.length; i += 4) {
            brightness += (0.2126 * pixels[i] + 0.7152 * pixels[i + 1] + 0.0722 * pixels[i + 2]) / 255;
        }

        brightness = brightness / count;

        for (y = 0; y < sampleHeight; y += 1) {
            for (x = 1; x < sampleWidth; x += 1) {
                var left = (y * sampleWidth + x - 1) * 4;
                var here = (y * sampleWidth + x) * 4;
                var leftLum = 0.2126 * pixels[left] + 0.7152 * pixels[left + 1] + 0.0722 * pixels[left + 2];
                var hereLum = 0.2126 * pixels[here] + 0.7152 * pixels[here + 1] + 0.0722 * pixels[here + 2];
                sharpness += Math.abs(hereLum - leftLum) / 255;
            }
        }

        sharpness = sharpness / Math.max(1, count - sampleHeight);

        return { brightness: brightness, sharpness: sharpness };
    }

    function loadImage(file) {
        return new Promise(function (resolve, reject) {
            var img = new Image();
            var url = URL.createObjectURL(file);

            img.onload = function () {
                URL.revokeObjectURL(url);
                resolve(img);
            };
            img.onerror = function () {
                URL.revokeObjectURL(url);
                reject(new Error("Image preview failed"));
            };
            img.src = url;
        });
    }

    async function reviewFile(state, file) {
        if (!file) {
            return;
        }

        if (!/^image\//i.test(file.type || "")) {
            setReview(state, {
                status: "accepted",
                score: null,
                warnings: [],
                dimensions: null,
                orientation: null,
                document_frame_ratio: null,
                reviewed_at: new Date().toISOString()
            });
            state.preview.dataset.visible = "false";
            return;
        }

        try {
            var img = await loadImage(file);
            displayPreview(state, img);
            setReview(state, analyzeImage(img, file, state.cardLike));
        } catch (error) {
            setReview(state, {
                status: "retake_recommended",
                score: 70,
                warnings: [warning("preview_failed", "We could not preview this image. Retake or choose a standard JPG, PNG, or PDF if possible.")],
                dimensions: null,
                orientation: null,
                document_frame_ratio: null,
                reviewed_at: new Date().toISOString()
            });
        }
    }

    async function startCamera(state) {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !canCaptureToFileInput()) {
            state.fileInput.click();
            return;
        }

        stopCamera(state);

        try {
            state.stream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: {
                    facingMode: { ideal: "environment" },
                    width: { ideal: 1920 },
                    height: { ideal: 1080 }
                }
            });
        } catch (error) {
            state.fileInput.click();
            return;
        }

        var video = document.createElement("video");
        video.autoplay = true;
        video.muted = true;
        video.playsInline = true;
        video.srcObject = state.stream;
        displayPreview(state, video);

        state.capture.classList.remove("cs-document-scanner__hidden");
        state.stop.classList.remove("cs-document-scanner__hidden");
        state.video = video;
    }

    function stopCamera(state) {
        if (state.stream) {
            state.stream.getTracks().forEach(function (track) {
                track.stop();
            });
        }

        state.stream = null;
        state.video = null;
        state.capture.classList.add("cs-document-scanner__hidden");
        state.stop.classList.add("cs-document-scanner__hidden");
    }

    async function captureCamera(state) {
        var video = state.video;

        if (!video || !video.videoWidth || !video.videoHeight) {
            return;
        }

        if (!canCaptureToFileInput()) {
            stopCamera(state);
            state.fileInput.click();
            return;
        }

        var source = cropForDocument(video.videoWidth, video.videoHeight, state.cardLike ? 1.586 : null);
        var canvas = document.createElement("canvas");
        var context = canvas.getContext("2d");

        canvas.width = source.width;
        canvas.height = source.height;

        if (!context) {
            return;
        }

        context.drawImage(video, source.x, source.y, source.width, source.height, 0, 0, source.width, source.height);

        canvas.toBlob(function (blob) {
            if (!blob) {
                return;
            }

            var file = new File([blob], "creditsoft-document-capture-" + Date.now() + ".jpg", {
                type: "image/jpeg",
                lastModified: Date.now()
            });
            var transfer = new DataTransfer();

            transfer.items.add(file);
            state.fileInput.files = transfer.files;
            state.fileInput.dispatchEvent(new Event("change", { bubbles: true }));
            stopCamera(state);
        }, "image/jpeg", 0.92);
    }

    function cropForDocument(width, height, targetRatio) {
        if (!targetRatio) {
            return { x: 0, y: 0, width: width, height: height };
        }

        var sourceRatio = width / height;
        var cropWidth = width;
        var cropHeight = height;

        if (sourceRatio > targetRatio) {
            cropWidth = Math.round(height * targetRatio);
        } else {
            cropHeight = Math.round(width / targetRatio);
        }

        return {
            x: Math.max(0, Math.round((width - cropWidth) / 2)),
            y: Math.max(0, Math.round((height - cropHeight) / 2)),
            width: cropWidth,
            height: cropHeight
        };
    }

    function enhance(form) {
        var fileInput;
        var label;
        var state;
        var panel;

        if (!form || enhancedForms.has(form)) {
            return null;
        }

        fileInput = form.querySelector(FILE_SELECTOR);

        if (!fileInput) {
            return null;
        }

        label = textFromForm(form, fileInput);
        panel = createPanel(isCardLike(label));
        insertPanel(fileInput, panel);

        if (!fileInput.accept) {
            fileInput.accept = "image/*,.pdf,.heic,.heif";
        }

        if (isCardLike(label) && form.dataset.creditsoftCameraCapture !== "false") {
            fileInput.setAttribute("capture", "environment");
        }

        state = {
            form: form,
            fileInput: fileInput,
            qualityInput: ensureQualityInput(form),
            cardLike: isCardLike(label),
            panel: panel,
            preview: panel.querySelector("[data-cs-preview]"),
            review: panel.querySelector("[data-cs-review]"),
            badge: panel.querySelector("[data-cs-badge]"),
            score: panel.querySelector("[data-cs-score]"),
            warnings: panel.querySelector("[data-cs-warnings]"),
            useAnyway: panel.querySelector("[data-cs-use-anyway]"),
            capture: document.createElement("button"),
            stop: document.createElement("button"),
            confirmed: false,
            result: null,
            stream: null,
            video: null
        };

        state.capture.type = "button";
        state.capture.className = "cs-document-scanner__button cs-document-scanner__button--primary cs-document-scanner__hidden";
        state.capture.textContent = "Capture photo";
        state.stop.type = "button";
        state.stop.className = "cs-document-scanner__button cs-document-scanner__hidden";
        state.stop.textContent = "Close camera";
        panel.querySelector(".cs-document-scanner__actions").appendChild(state.capture);
        panel.querySelector(".cs-document-scanner__actions").appendChild(state.stop);

        panel.querySelector("[data-cs-scan]").addEventListener("click", function () {
            startCamera(state);
        });
        panel.querySelector("[data-cs-retake]").addEventListener("click", function () {
            stopCamera(state);
            fileInput.click();
        });
        state.useAnyway.addEventListener("click", function () {
            state.confirmed = true;
            state.badge.textContent = "Ready to upload";
            state.useAnyway.classList.add("cs-document-scanner__hidden");
        });
        state.capture.addEventListener("click", function () {
            captureCamera(state);
        });
        state.stop.addEventListener("click", function () {
            stopCamera(state);
        });
        fileInput.addEventListener("change", function () {
            state.cardLike = isCardLike(textFromForm(form, fileInput));
            reviewFile(state, fileInput.files && fileInput.files[0]);
        });
        form.addEventListener("submit", function (event) {
            if (state.result && state.result.status === "retake_recommended" && !state.confirmed) {
                event.preventDefault();
                state.review.scrollIntoView({ behavior: "smooth", block: "center" });
                state.useAnyway.classList.remove("cs-document-scanner__hidden");
                state.useAnyway.focus();
            }
        });

        enhancedForms.add(form);

        if (fileInput.files && fileInput.files[0]) {
            reviewFile(state, fileInput.files[0]);
        }

        return state;
    }

    function autoEnhance() {
        var forms = Array.prototype.slice.call(document.querySelectorAll(FORM_SELECTOR));

        Array.prototype.slice.call(document.querySelectorAll("form")).forEach(function (form) {
            var fileInput = form.querySelector(FILE_SELECTOR);
            var action = (form.getAttribute("action") || "").toLowerCase();

            if (fileInput && (fileInput.name === "document_file" || action.indexOf("/documents") !== -1)) {
                forms.push(form);
            }
        });

        forms.forEach(enhance);
    }

    window.CreditSoftDocumentScanner = {
        enhance: enhance,
        autoEnhance: autoEnhance
    };

    ready(autoEnhance);
}());
