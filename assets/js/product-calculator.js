(function($) {
    "use strict";

    var calc = {
        params: wcms_params,
        files: [],
        $upload: null,
        $fileList: null,
        $loading: null,
        $loadingText: null,
        $canvas: null,
        $previewBtn: null,
        $optimizeBtn: null,
        $summary: null,
        $imagesData: null,
        $addToCart: null,
        $minNotice: null,
        $filmWidthDisplay: null,
        lastNesting: null,
        optimizedLayout: null,
        isOptimized: false,
        _pdfJsLoaded: false,

        init: function() {
            this.$upload = $("#wcms-upload");
            this.$fileList = $("#wcms-file-list");
            this.$loading = $("#wcms-loading");
            this.$loadingText = $("#wcms-loading .wcms-loading-text");
            this.$canvas = $("#wcms-preview-canvas");
            this.$previewBtn = $("#wcms-preview-btn");
            this.$optimizeBtn = $("#wcms-optimize-btn");
            this.$summary = $("#wcms-summary");
            this.$imagesData = $("#wcms_images_data");
            this.$addToCart = $("#wcms-add-to-cart");
            this.$minNotice = $("#wcms-min-notice");
            this.$filmWidthDisplay = $("#wcms-film-width-display");

            this.$filmWidthDisplay.text(this.params.film_width);

            this.$upload.on("change", $.proxy(this.onUpload, this));
            this.$fileList.on("click", ".wcms-file-remove", $.proxy(this.onRemoveFile, this));
            this.$fileList.on("input", ".wcms-file-w", $.proxy(this.onWidthChange, this));
            this.$fileList.on("input", ".wcms-file-h", $.proxy(this.onHeightChange, this));
            this.$fileList.on("input", ".wcms-file-copies", $.proxy(this.onCopiesChange, this));
            this.$fileList.on("input", ".wcms-file-title", $.proxy(this.onTitleChange, this));
            this.$previewBtn.on("click", $.proxy(this.showLightbox, this));
            this.$optimizeBtn.on("click", $.proxy(this.onOptimize, this));
            $("form.cart").on("submit", $.proxy(this.onAddToCart, this));
        },

        onUpload: function(e) {
            var files = e.target.files;
            if (!files || !files.length) return;

            for (var i = 0; i < files.length; i++) {
                this.processFile(files[i]);
            }
            this.$upload.val("");
        },

        processFile: function(file) {
            var ext = file.name.split(".").pop().toLowerCase();
            var self = this;

            if (ext === "png" || ext === "jpg" || ext === "jpeg") {
                this.readImage(file);
            } else if (ext === "pdf") {
                if (this.params.enable_pdf !== "yes") {
                    alert("PDF upload is disabled.");
                    return;
                }
                this.readPDF(file);
            }
        },

        addFileEntry: function(fileEntry) {
            this.files.push(fileEntry);
            this.renderFileList();
        },

        renderFileList: function() {
            var html = "";
            for (var i = 0; i < this.files.length; i++) {
                var f = this.files[i];
                var filmW = parseFloat(this.params.film_width);
                var warnClass = "";
                var warnMsg = "";
                if (f.dpiWarning) {
                    warnClass = "wcms-dpi-warn";
                    warnMsg = '<div class="wcms-dpi-warning-text">' + (this.params.i18n.dpi_warning || "Warning: Resolution below 150 DPI") + "</div>";
                } else if (f.filmWarn) {
                    warnClass = "wcms-dpi-warn";
                    warnMsg = '<div class="wcms-dpi-warning-text">' + (this.params.i18n.film_warn || "Dimension adjusted to fit film width of ") + filmW + " cm</div>";
                } else {
                    warnClass = "wcms-dpi-ok";
                }
                html += '<div class="wcms-file-entry" data-index="' + i + '">';
                html += '<div class="wcms-file-entry-header">';
                html += '<img class="wcms-file-entry-thumb" src="' + f.thumb + '" />';
                html += '<span class="wcms-file-entry-name">' + this.escapeHtml(f.name) + "</span>";
                html += '<button type="button" class="wcms-file-remove" title="' + (this.params.i18n.remove || "Remove") + '">&times;</button>';
                html += "</div>";
                html += '<div class="wcms-file-entry-title">';
                html += '<input type="text" class="wcms-file-title" data-index="' + i + '" value="' + this.escapeHtml(f.title || f.name) + '" placeholder="' + (this.params.i18n.title_placeholder || "Image title") + '" />';
                html += "</div>";
                html += '<div class="wcms-file-entry-dims">';
                html += '<input type="number" class="wcms-file-w" data-index="' + i + '" step="0.1" min="0.1" value="' + f.print_w.toFixed(1) + '" />';
                html += '<span>x</span>';
                html += '<input type="number" class="wcms-file-h" data-index="' + i + '" step="0.1" min="0.1" value="' + f.print_h.toFixed(1) + '" />';
                html += '<span>cm</span>';
                html += "</div>";
                html += '<div class="wcms-file-entry-copies">';
                html += '<label>' + (this.params.i18n.copies || "Copies") + ':</label>';
                html += '<input type="number" class="wcms-file-copies" data-index="' + i + '" min="1" step="1" value="' + (f.copies || 1) + '" />';
                html += "</div>";
                html += '<div class="wcms-file-entry-dpi ' + warnClass + '">';
                html += (this.params.i18n.dpi_label || "DPI") + ": " + (f.dpi || "?") + " | " + f.pixel_w + "x" + f.pixel_h + "px";
                html += "</div>";
                html += warnMsg;
                html += "</div>";
            }
            if (html === "") {
                html = '<p class="wcms-no-files">' + (this.params.i18n.no_files || "No files uploaded yet.") + "</p>";
            }
            this.$fileList.html(html);
        },

        onRemoveFile: function(e) {
            var $entry = $(e.target).closest(".wcms-file-entry");
            var index = parseInt($entry.data("index"));
            this.files.splice(index, 1);
            this.renderFileList();
            this.calculate();
        },

        onWidthChange: function(e) {
            var $input = $(e.target);
            var index = parseInt($input.closest(".wcms-file-entry").data("index"));
            var f = this.files[index];
            if (!f) return;

            var w = parseFloat($input.val()) || 0;
            if (w > 0 && f.pixel_w > 0) {
                var aspect = f.pixel_w / f.pixel_h;
                var h = w / aspect;
                f.print_w = w;
                f.print_h = h;
                f.dpi = this.calcDPI(f.pixel_w, w);
                if (f.dpi < 150) {
                    f.dpiWarning = true;
                    w = f.dpi > 0 ? (f.pixel_w / 150 * 2.54) : w;
                    f.print_w = w;
                    f.print_h = w / aspect;
                    f.dpi = this.calcDPI(f.pixel_w, f.print_w);
                    f.dpiWarning = f.dpi < 150;
                    $input.val(f.print_w.toFixed(1));
                } else {
                    f.dpiWarning = false;
                }
                this.clampToFilm(f);
                if (f.filmWarn) {
                    $input.closest(".wcms-file-entry").find(".wcms-file-w").val(f.print_w.toFixed(1));
                }
                $input.closest(".wcms-file-entry").find(".wcms-file-h").val(f.print_h.toFixed(1));
            }
            this.updateEntryDPI(index);
            this.calculate();
        },

        onHeightChange: function(e) {
            var $input = $(e.target);
            var index = parseInt($input.closest(".wcms-file-entry").data("index"));
            var f = this.files[index];
            if (!f) return;

            var h = parseFloat($input.val()) || 0;
            if (h > 0 && f.pixel_h > 0) {
                var aspect = f.pixel_w / f.pixel_h;
                var w = h * aspect;
                f.print_h = h;
                f.print_w = w;
                f.dpi = this.calcDPI(f.pixel_h, h);
                if (f.dpi < 150) {
                    f.dpiWarning = true;
                    h = f.dpi > 0 ? (f.pixel_h / 150 * 2.54) : h;
                    f.print_h = h;
                    f.print_w = h * aspect;
                    f.dpi = this.calcDPI(f.pixel_h, f.print_h);
                    f.dpiWarning = f.dpi < 150;
                    $input.val(f.print_h.toFixed(1));
                } else {
                    f.dpiWarning = false;
                }
                this.clampToFilm(f);
                if (f.filmWarn) {
                    $input.closest(".wcms-file-entry").find(".wcms-file-h").val(f.print_h.toFixed(1));
                }
                $input.closest(".wcms-file-entry").find(".wcms-file-w").val(f.print_w.toFixed(1));
            }
            this.updateEntryDPI(index);
            this.calculate();
        },

        onCopiesChange: function(e) {
            var $input = $(e.target);
            var index = parseInt($input.closest(".wcms-file-entry").data("index"));
            var f = this.files[index];
            if (!f) return;
            f.copies = parseInt($input.val()) || 1;
            if (f.copies < 1) f.copies = 1;
            this.calculate();
        },

        onTitleChange: function(e) {
            var $input = $(e.target);
            var index = parseInt($input.closest(".wcms-file-entry").data("index"));
            var f = this.files[index];
            if (!f) return;
            f.title = $input.val() || f.name.replace(/\.[^.]+$/, "");
        },

        clampToFilm: function(entry) {
            var filmW = parseFloat(this.params.film_width);
            if (filmW <= 0) return;
            var maxDim = Math.max(entry.print_w, entry.print_h);
            if (maxDim > filmW) {
                var aspect = entry.pixel_w / entry.pixel_h;
                if (entry.print_w > entry.print_h) {
                    entry.print_w = filmW;
                    entry.print_h = filmW / aspect;
                } else {
                    entry.print_h = filmW;
                    entry.print_w = filmW * aspect;
                }
                entry.dpi = this.calcDPI(entry.pixel_w, entry.print_w);
                entry.filmWarn = true;
                if (entry.dpi < 150) {
                    entry.dpiWarning = true;
                } else {
                    entry.dpiWarning = false;
                }
            } else {
                entry.filmWarn = false;
            }
        },

        calcDPI: function(pixels, cm) {
            if (cm <= 0) return 0;
            return Math.round(pixels / (cm / 2.54));
        },

        updateEntryDPI: function(index) {
            var f = this.files[index];
            if (!f) return;
            var $entry = this.$fileList.find('.wcms-file-entry[data-index="' + index + '"]');
            var $dpi = $entry.find(".wcms-file-entry-dpi");
            var $oldWarn = $entry.find(".wcms-dpi-warning-text");
            $dpi.text((this.params.i18n.dpi_label || "DPI") + ": " + (f.dpi || "?") + " | " + f.pixel_w + "x" + f.pixel_h + "px");
            $dpi.removeClass("wcms-dpi-ok wcms-dpi-warn");
            $oldWarn.remove();
            if (f.dpiWarning) {
                $dpi.addClass("wcms-dpi-warn");
                $dpi.after('<div class="wcms-dpi-warning-text">' + (this.params.i18n.dpi_warning || "Warning: Resolution below 150 DPI") + "</div>");
            } else if (f.filmWarn) {
                $dpi.addClass("wcms-dpi-warn");
                var filmW = parseFloat(this.params.film_width);
                $dpi.after('<div class="wcms-dpi-warning-text">' + (this.params.i18n.film_warn || "Dimension adjusted to fit film width of ") + filmW + " cm</div>");
            } else {
                $dpi.addClass("wcms-dpi-ok");
            }
        },

        readImage: function(file) {
            var self = this;
            var reader = new FileReader();
            reader.onload = function(ev) {
                var img = new Image();
                img.onload = function() {
                    var dpi = parseInt(self.params.dpi) || 300;
                    var wCm = img.width / dpi * 2.54;
                    var hCm = img.height / dpi * 2.54;
                    wCm = Math.round(wCm * 10) / 10;
                    hCm = Math.round(hCm * 10) / 10;
                    if (wCm < 0.1) wCm = 0.1;
                    if (hCm < 0.1) hCm = 0.1;
                    var entry = {
                        name: file.name,
                        title: file.name.replace(/\.[^.]+$/, ""),
                        thumb: ev.target.result,
                        image: img,
                        pixel_w: img.width,
                        pixel_h: img.height,
                        print_w: wCm,
                        print_h: hCm,
                        copies: 1,
                        dpi: dpi,
                        dpiWarning: false,
                        filmWarn: false,
                    };
                    entry.dpi = self.calcDPI(entry.pixel_w, entry.print_w);
                    self.clampToFilm(entry);
                    self.addFileEntry(entry);
                    self.calculate();
                };
                img.src = ev.target.result;
            };
            reader.readAsDataURL(file);
        },

        readPDF: function(file) {
            var self = this;
            if (typeof pdfjsLib === "undefined") {
                this._loadPDFJs(function() {
                    self.readPDF(file);
                });
                return;
            }
            this.$loading.show();
            this.$loadingText.text("Processing PDF...");
            var reader = new FileReader();
            reader.onload = function(ev) {
                var loadingTask = pdfjsLib.getDocument({ data: ev.target.result });
                loadingTask.promise.then(function(pdf) {
                    return pdf.getPage(1);
                }).then(function(page) {
                    var vp = page.getViewport({ scale: 1.5 });
                    var canvas = document.createElement("canvas");
                    canvas.width = vp.width;
                    canvas.height = vp.height;
                    var ctx = canvas.getContext("2d");
                    return page.render({ canvasContext: ctx, viewport: vp }).promise.then(function() {
                        return { page: page, canvas: canvas, vp: vp };
                    });
                }).then(function(result) {
                    var dataUrl = result.canvas.toDataURL("image/png");
                    var img = new Image();
                    img.onload = function() {
                        self.$loading.hide();
                        var vp = result.vp;
                        var wCm = Math.round(vp.width / 72 * 2.54 * 10) / 10;
                        var hCm = Math.round(vp.height / 72 * 2.54 * 10) / 10;
                        if (wCm < 0.1) wCm = 0.1;
                        if (hCm < 0.1) hCm = 0.1;
                        var dpi = parseInt(self.params.dpi) || 300;
                        var entry = {
                            name: file.name,
                            title: file.name.replace(/\.[^.]+$/, ""),
                            thumb: dataUrl,
                            image: img,
                            pixel_w: Math.round(vp.width),
                            pixel_h: Math.round(vp.height),
                            print_w: wCm,
                            print_h: hCm,
                            copies: 1,
                            dpi: dpi,
                            dpiWarning: false,
                            filmWarn: false,
                        };
                        entry.dpi = self.calcDPI(entry.pixel_w, entry.print_w);
                        self.clampToFilm(entry);
                        self.addFileEntry(entry);
                        self.calculate();
                    };
                    img.src = dataUrl;
                }).catch(function() {
                    self._fallbackPDF(file);
                });
            };
            reader.readAsArrayBuffer(file);
        },

        _fallbackPDF: function(file) {
            var self = this;
            var reader = new FileReader();
            reader.onload = function(ev) {
                var bytes = new Uint8Array(ev.target.result);
                var text = "";
                for (var i = 0; i < bytes.length; i++) {
                    text += String.fromCharCode(bytes[i]);
                }
                var match = text.match(/\/MediaBox\s*\[\s*(\d+\.?\d*)\s+(\d+\.?\d*)\s+(\d+\.?\d*)\s+(\d+\.?\d*)\s*\]/);
                var wCm = 10, hCm = 15, wPx = 300, hPx = 450;
                if (match) {
                    var w = parseFloat(match[3]) - parseFloat(match[1]);
                    var h = parseFloat(match[4]) - parseFloat(match[2]);
                    wCm = Math.round(w / 72 * 2.54 * 10) / 10;
                    hCm = Math.round(h / 72 * 2.54 * 10) / 10;
                    wPx = Math.round(w);
                    hPx = Math.round(h);
                }
                if (wCm < 0.1) wCm = 0.1;
                if (hCm < 0.1) hCm = 0.1;
                self.$loading.hide();
                var dpi = parseInt(self.params.dpi) || 300;
                var entry = {
                    name: file.name,
                    title: file.name.replace(/\.[^.]+$/, ""),
                    thumb: "",
                    image: null,
                    pixel_w: wPx,
                    pixel_h: hPx,
                    print_w: wCm,
                    print_h: hCm,
                    copies: 1,
                    dpi: dpi,
                    dpiWarning: false,
                    filmWarn: false,
                };
                entry.dpi = self.calcDPI(entry.pixel_w, entry.print_w);
                self.clampToFilm(entry);
                self.addFileEntry(entry);
                self.calculate();
            };
            reader.readAsArrayBuffer(file);
        },

        _loadPDFJs: function(callback) {
            var self = this;
            if (this._pdfJsLoaded) { callback(); return; }
            var script = document.createElement("script");
            script.src = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js";
            script.onload = function() {
                pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js";
                self._pdfJsLoaded = true;
                callback();
            };
            script.onerror = function() {
                self._fallbackPDF(null);
            };
            document.head.appendChild(script);
        },

        calculate: function() {
            var images = [];
            var imagesWithTitles = [];
            for (var i = 0; i < this.files.length; i++) {
                var f = this.files[i];
                if (f.print_w > 0 && f.print_h > 0 && f.copies > 0) {
                    images.push({
                        img_w: f.print_w,
                        img_h: f.print_h,
                        copies: f.copies
                    });
                    imagesWithTitles.push({
                        img_w: f.print_w,
                        img_h: f.print_h,
                        copies: f.copies,
                        title: f.title || f.name
                    });
                }
            }

            if (images.length === 0) {
                this.$summary.find(".wcms-summary-value").first().text("0");
                this.$summary.find("#wcms-summary-meters").text("0.00 m");
                this.$imagesData.val("");
                this.$optimizeBtn.hide();
                return;
            }

            this.optimizedLayout = null;
            this.isOptimized = false;
            var self = this;

            $.ajax({
                url: this.params.ajax_url,
                type: "POST",
                data: {
                    action: "wcms_calculate_multi",
                    nonce: this.params.nonce,
                    product_id: this.params.product_id,
                    images: JSON.stringify(images)
                },
                success: function(res) {
                    if (self.params.enable_debug === "yes") {
                        console.log("[WCMS] Multi AJAX response:", res);
                    }
                    if (res.success) {
                        self.lastNesting = res.data.nesting;
                        self.updateSummary(res.data, images);
                        self.drawPreview(res.data.nesting);
                        self.$previewBtn.show();
                        self.$optimizeBtn.show().text(self.params.i18n.optimize_btn || "Optimize layout");

                        self.$imagesData.val(JSON.stringify(imagesWithTitles));
                    }
                }
            });
        },

        onOptimize: function() {
            if (this.files.length < 2) return;
            this.$loading.show();
            this.$loadingText.text(this.params.i18n.calculating || "Calculating optimized layout...");
            var self = this;

            setTimeout(function() {
                var result = self.optimizeLayout();
                self.$loading.hide();
                if (!result) return;

                self.optimizedLayout = result;
                self.isOptimized = true;
                self.lastNesting = {
                    total_length_cm: result.total_length_cm,
                    total_length_m: result.total_length_m,
                    images: [],
                };

                self.drawOptimizedPreview(result);
                self.$previewBtn.show();
                self.$optimizeBtn.text((self.params.i18n.optimized_label || "Optimized") + " (" + result.total_length_m.toFixed(2) + "m)");

                self.ajaxCalculatePrice(result.total_length_m, function(data) {
                    var fakeRes = {
                        success: true,
                        data: {
                            nesting: self.lastNesting,
                            total_images: self.files.length,
                            total_copies: (function() {
                                var t = 0;
                                for (var i = 0; i < self.files.length; i++) t += (self.files[i].copies || 1);
                                return t;
                            })(),
                            formatted: data.formatted,
                            min_notice: data.min_notice,
                        }
                    };
                    self.updateSummary(fakeRes.data, []);
                    var imagesArr = [];
                    for (var ii = 0; ii < self.files.length; ii++) {
                        var ff = self.files[ii];
                        imagesArr.push({ img_w: ff.print_w, img_h: ff.print_h, copies: ff.copies || 1, title: ff.title || ff.name });
                    }
                    self.$imagesData.val(JSON.stringify({
                        optimized: true,
                        total_length_m: result.total_length_m,
                        length_cm: result.total_length_cm,
                        images: imagesArr,
                        rows: result.rows,
                    }));
                });
            }, 100);
        },

        optimizeLayout: function() {
            var filmW = parseFloat(this.params.film_width);
            var gap = parseFloat(this.params.gap || 0.5);
            var waste = parseFloat(this.params.waste || 5) / 100;

            var items = [];
            for (var i = 0; i < this.files.length; i++) {
                var f = this.files[i];
                if (f.print_w <= 0 || f.print_h <= 0 || f.copies <= 0) continue;
                for (var c = 0; c < f.copies; c++) {
                    items.push({ w: f.print_w, h: f.print_h, imgIdx: i });
                }
            }
            if (items.length === 0) return null;

            var strategies = [
                { sort: function(a,b) { return b.h - a.h || b.w - a.w; } },
                { sort: function(a,b) { return b.w - a.w || b.h - a.h; } },
                { sort: function(a,b) { return (b.w*b.h) - (a.w*a.h); } },
            ];

            var best = null;
            var bestLen = Infinity;

            for (var s = 0; s < strategies.length; s++) {
                var sorted = items.slice().sort(strategies[s].sort);
                var rows = this.stripPack(sorted, filmW, gap);
                var rawH = this.calcRowsHeight(rows, gap);
                var totalH = rawH * (1 + waste);
                if (totalH < bestLen) {
                    bestLen = totalH;
                    best = { rows: rows, total_length_cm: totalH, total_length_m: totalH / 100, raw_length_cm: rawH };
                }
            }

            return best;
        },

        stripPack: function(items, filmW, gap) {
            var rows = [];
            for (var i = 0; i < items.length; i++) {
                var item = items[i];
                var placed = false;
                for (var r = 0; r < rows.length; r++) {
                    var row = rows[r];
                    var needed = item.w + (row.items.length > 0 ? gap : 0);
                    if (row.usedWidth + needed <= filmW) {
                        var x = row.usedWidth + (row.items.length > 0 ? gap : 0);
                        row.items.push({ x: x, w: item.w, h: item.h, imgIdx: item.imgIdx });
                        row.usedWidth = x + item.w;
                        row.height = Math.max(row.height, item.h);
                        placed = true;
                        break;
                    }
                }
                if (!placed) {
                    rows.push({
                        items: [{ x: 0, w: item.w, h: item.h, imgIdx: item.imgIdx }],
                        usedWidth: item.w,
                        height: item.h,
                    });
                }
            }
            return rows;
        },

        calcRowsHeight: function(rows, gap) {
            var total = 0;
            for (var r = 0; r < rows.length; r++) {
                total += rows[r].height;
                if (r < rows.length - 1) total += gap;
            }
            return total;
        },

        ajaxCalculatePrice: function(meters, callback) {
            var self = this;
            $.ajax({
                url: this.params.ajax_price_url,
                type: "POST",
                data: {
                    action: "wcms_calculate_price",
                    nonce: this.params.nonce,
                    product_id: this.params.product_id,
                    meters: meters,
                },
                success: function(res) {
                    if (self.params.enable_debug === "yes") {
                        console.log("[WCMS] Price response:", res);
                    }
                    if (res.success && callback) {
                        callback(res.data);
                    }
                },
            });
        },

        drawOptimizedPreview: function(layout) {
            var canvas = this.$canvas[0];
            if (!canvas) return;

            var filmW = parseFloat(this.params.film_width);
            var gap = parseFloat(this.params.gap || 0.5);
            var container = canvas.parentElement;
            var containerW = container.clientWidth || 570;
            var pad = 20;

            var totalLengthCm = layout.total_length_cm || 0;
            var scale = (containerW - pad * 2) / filmW;
            var canvasH = Math.max(totalLengthCm * scale + pad * 2, 200);

            canvas.width = containerW;
            canvas.height = canvasH;

            var ctx = canvas.getContext("2d");
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            ctx.fillStyle = this.params.canvas_bg || "#f5f5f5";
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            var ox = pad;
            var oy = pad;
            var totalW = filmW * scale;
            var totalH = totalLengthCm * scale;

            ctx.fillStyle = "#ffffff";
            ctx.strokeStyle = "#999";
            ctx.lineWidth = 1;
            ctx.fillRect(ox, oy, totalW, totalH);
            ctx.strokeRect(ox, oy, totalW, totalH);

            var currentY = oy;
            var rows = layout.rows || [];

            for (var r = 0; r < rows.length; r++) {
                var row = rows[r];
                var rowH = row.height * scale;

                for (var i = 0; i < row.items.length; i++) {
                    var item = row.items[i];
                    var x = ox + item.x * scale;
                    var y = currentY;
                    var w = item.w * scale;
                    var h = item.h * scale;

                    var imgObj = (this.files[item.imgIdx] && this.files[item.imgIdx].image) ? this.files[item.imgIdx].image : null;

                    if (imgObj) {
                        ctx.save();
                        ctx.beginPath();
                        ctx.rect(x, y, w, h);
                        ctx.clip();
                        ctx.drawImage(imgObj, x, y, w, h);
                        ctx.restore();
                    } else {
                        var colors = (this.params.rect_colors || "#e74c3c,#3498db,#2ecc71,#f39c12,#9b59b6").split(",");
                        ctx.fillStyle = colors[item.imgIdx % colors.length];
                        ctx.globalAlpha = 0.3;
                        ctx.fillRect(x, y, w, h);
                        ctx.globalAlpha = 1.0;
                    }

                    ctx.strokeStyle = "#333";
                    ctx.lineWidth = 1;
                    ctx.strokeRect(x, y, w, h);
                }

                currentY += row.height * scale + (r < rows.length - 1 ? gap * scale : 0);
            }

            ctx.fillStyle = "#333";
            ctx.font = "11px sans-serif";
            ctx.textAlign = "center";
            var totalM = totalLengthCm / 100;
            ctx.fillText(filmW + " cm  |  " + totalM.toFixed(2) + " m  |  " + (this.params.i18n.optimized_label || "Optimized"), ox + totalW / 2, oy + totalH + 14);
        },

        updateSummary: function(data, images) {
            var totalImages = data.total_images || images.length || 0;
            var totalCopies = data.total_copies || 0;

            $("#wcms-summary-images").text(totalImages + " (" + totalCopies + " " + (this.params.i18n.copies_label || "copies") + ")");
            $("#wcms-summary-meters").text(data.formatted.meters + " m");
            $("#wcms-summary-fixed").text(data.formatted.fixed);
            $("#wcms-summary-rate").text(data.formatted.rate);
            $("#wcms-summary-total").text(data.formatted.total);

            if (data.min_notice) {
                this.$minNotice.text(data.min_notice).show();
            } else {
                this.$minNotice.hide();
            }

            this.$summary.show();
        },

        drawPreview: function(nesting) {
            var canvas = this.$canvas[0];
            if (!canvas) return;

            var filmW = parseFloat(this.params.film_width);
            var gap = parseFloat(this.params.gap || 0.5);
            var container = canvas.parentElement;
            var containerW = container.clientWidth || 570;
            var pad = 20;

            var totalLengthCm = nesting.total_length_cm || 0;
            var scale = (containerW - pad * 2) / filmW;
            var canvasH = Math.max(totalLengthCm * scale + pad * 2, 200);

            canvas.width = containerW;
            canvas.height = canvasH;

            var ctx = canvas.getContext("2d");
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            ctx.fillStyle = this.params.canvas_bg || "#f5f5f5";
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            var ox = pad;
            var oy = pad;
            var totalW = filmW * scale;
            var totalH = totalLengthCm * scale;

            ctx.fillStyle = "#ffffff";
            ctx.strokeStyle = "#999";
            ctx.lineWidth = 1;
            ctx.fillRect(ox, oy, totalW, totalH);
            ctx.strokeRect(ox, oy, totalW, totalH);

            var colors = (this.params.rect_colors || "#e74c3c,#3498db,#2ecc71,#f39c12,#9b59b6").split(",");
            var currentY = oy;

            var imagesResult = nesting.images || [];
            for (var idx = 0; idx < imagesResult.length; idx++) {
                var n = imagesResult[idx];
                var imgObj = (this.files[idx] && this.files[idx].image) ? this.files[idx].image : null;
                var color = colors[idx % colors.length];

                for (var row = 0; row < n.rows; row++) {
                    for (var col = 0; col < n.across; col++) {
                        var drawn = row * n.across + col;
                        if (drawn >= n.copies) break;

                        var x = ox + col * (n.img_w + gap) * scale;
                        var y = currentY + row * (n.img_h + gap) * scale;
                        var w = n.img_w * scale;
                        var h = n.img_h * scale;

                        if (imgObj) {
                            ctx.save();
                            ctx.beginPath();
                            ctx.rect(x, y, w, h);
                            ctx.clip();
                            if (n.rotated) {
                                ctx.translate(x + w / 2, y + h / 2);
                                ctx.rotate(Math.PI / 2);
                                ctx.drawImage(imgObj, -h / 2, -w / 2, h, w);
                            } else {
                                ctx.drawImage(imgObj, x, y, w, h);
                            }
                            ctx.restore();
                            ctx.strokeStyle = "#333";
                            ctx.lineWidth = 1;
                            ctx.strokeRect(x, y, w, h);
                        } else {
                            ctx.fillStyle = color;
                            ctx.globalAlpha = 0.3;
                            ctx.fillRect(x, y, w, h);
                            ctx.globalAlpha = 1.0;
                            ctx.strokeStyle = "#333";
                            ctx.lineWidth = 1;
                            ctx.strokeRect(x, y, w, h);
                        }
                    }
                }

                currentY += n.length_cm * scale;
            }

            ctx.fillStyle = "#333";
            ctx.font = "11px sans-serif";
            ctx.textAlign = "center";
            var totalM = totalLengthCm / 100;
            ctx.fillText(filmW + " cm  |  " + totalM.toFixed(2) + " m", ox + totalW / 2, oy + totalH + 14);
        },

        showLightbox: function() {
            if (!this.lastNesting && !this.optimizedLayout) return;

            var self = this;
            var body = document.body;

            var overlay = document.createElement("div");
            overlay.className = "wcms-lightbox-overlay";

            var modal = document.createElement("div");
            modal.className = "wcms-lightbox-modal";

            var close = document.createElement("button");
            close.className = "wcms-lightbox-close";
            close.innerHTML = "&times;";
            close.onclick = function() { self.closeLightbox(overlay); };

            var canvas = document.createElement("canvas");
            canvas.id = "wcms-lightbox-canvas";

            modal.appendChild(close);
            modal.appendChild(canvas);
            overlay.appendChild(modal);
            body.appendChild(overlay);

            overlay.classList.add("active");

            requestAnimationFrame(function() {
                var vw = window.innerWidth;
                var vh = window.innerHeight;
                canvas.width = Math.round(vw * 0.88);
                canvas.height = Math.round(vh * 0.80);
                if (self.isOptimized && self.optimizedLayout) {
                    self.drawOptimizedPreviewOnCanvas(canvas, self.optimizedLayout);
                } else {
                    self.drawPreviewOnCanvas(canvas, self.lastNesting);
                }
            });

            overlay.onclick = function(e) {
                if (e.target === overlay) {
                    self.closeLightbox(overlay);
                }
            };
            document.addEventListener("keydown", function handler(e) {
                if (e.key === "Escape") {
                    self.closeLightbox(overlay);
                    document.removeEventListener("keydown", handler);
                }
            });
        },

        closeLightbox: function(overlay) {
            if (overlay && overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
        },

        drawPreviewOnCanvas: function(canvas, nesting) {
            var filmW = parseFloat(this.params.film_width);
            var gap = parseFloat(this.params.gap || 0.5);
            var pad = 24;
            var totalLengthCm = nesting.total_length_cm || 0;

            var scaleX = (canvas.width - pad * 2) / filmW;
            var scaleY = (canvas.height - pad * 2) / totalLengthCm;
            var scale = Math.min(scaleX, scaleY);
            var ox = pad + ((canvas.width - pad * 2) - filmW * scale) / 2;
            var oy = pad + ((canvas.height - pad * 2) - totalLengthCm * scale) / 2;

            var ctx = canvas.getContext("2d");
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            ctx.fillStyle = this.params.canvas_bg || "#f5f5f5";
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            var totalW = filmW * scale;
            var totalH = totalLengthCm * scale;

            ctx.fillStyle = "#ffffff";
            ctx.strokeStyle = "#999";
            ctx.lineWidth = 1;
            ctx.fillRect(ox, oy, totalW, totalH);
            ctx.strokeRect(ox, oy, totalW, totalH);

            var colors = (this.params.rect_colors || "#e74c3c,#3498db,#2ecc71,#f39c12,#9b59b6").split(",");
            var currentY = oy;

            var imagesResult = nesting.images || [];
            for (var idx = 0; idx < imagesResult.length; idx++) {
                var n = imagesResult[idx];
                var imgObj = (this.files[idx] && this.files[idx].image) ? this.files[idx].image : null;
                var color = colors[idx % colors.length];

                for (var row = 0; row < n.rows; row++) {
                    for (var col = 0; col < n.across; col++) {
                        var drawn = row * n.across + col;
                        if (drawn >= n.copies) break;

                        var x = ox + col * (n.img_w + gap) * scale;
                        var y = currentY + row * (n.img_h + gap) * scale;
                        var w = n.img_w * scale;
                        var h = n.img_h * scale;

                        if (imgObj) {
                            ctx.save();
                            ctx.beginPath();
                            ctx.rect(x, y, w, h);
                            ctx.clip();
                            if (n.rotated) {
                                ctx.translate(x + w / 2, y + h / 2);
                                ctx.rotate(Math.PI / 2);
                                ctx.drawImage(imgObj, -h / 2, -w / 2, h, w);
                            } else {
                                ctx.drawImage(imgObj, x, y, w, h);
                            }
                            ctx.restore();
                            ctx.strokeStyle = "#333";
                            ctx.lineWidth = 1;
                            ctx.strokeRect(x, y, w, h);
                        } else {
                            ctx.fillStyle = color;
                            ctx.globalAlpha = 0.3;
                            ctx.fillRect(x, y, w, h);
                            ctx.globalAlpha = 1.0;
                            ctx.strokeStyle = "#333";
                            ctx.lineWidth = 1;
                            ctx.strokeRect(x, y, w, h);
                        }
                    }
                }

                currentY += n.length_cm * scale;
            }

            ctx.fillStyle = "#333";
            ctx.font = "14px sans-serif";
            ctx.textAlign = "center";
            var totalM = totalLengthCm / 100;
            ctx.fillText(filmW + " cm  |  " + totalM.toFixed(2) + " m", ox + totalW / 2, oy + totalH + 20);
        },

        drawOptimizedPreviewOnCanvas: function(canvas, layout) {
            var filmW = parseFloat(this.params.film_width);
            var gap = parseFloat(this.params.gap || 0.5);
            var pad = 24;
            var totalLengthCm = layout.total_length_cm || 0;

            var scaleX = (canvas.width - pad * 2) / filmW;
            var scaleY = (canvas.height - pad * 2) / totalLengthCm;
            var scale = Math.min(scaleX, scaleY);
            var ox = pad + ((canvas.width - pad * 2) - filmW * scale) / 2;
            var oy = pad + ((canvas.height - pad * 2) - totalLengthCm * scale) / 2;

            var ctx = canvas.getContext("2d");
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            ctx.fillStyle = this.params.canvas_bg || "#f5f5f5";
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            var totalW = filmW * scale;
            var totalH = totalLengthCm * scale;

            ctx.fillStyle = "#ffffff";
            ctx.strokeStyle = "#999";
            ctx.lineWidth = 1;
            ctx.fillRect(ox, oy, totalW, totalH);
            ctx.strokeRect(ox, oy, totalW, totalH);

            var currentY = oy;
            var rows = layout.rows || [];

            for (var r = 0; r < rows.length; r++) {
                var row = rows[r];

                for (var i = 0; i < row.items.length; i++) {
                    var item = row.items[i];
                    var x = ox + item.x * scale;
                    var y = currentY;
                    var w = item.w * scale;
                    var h = item.h * scale;

                    var imgObj = (this.files[item.imgIdx] && this.files[item.imgIdx].image) ? this.files[item.imgIdx].image : null;

                    if (imgObj) {
                        ctx.save();
                        ctx.beginPath();
                        ctx.rect(x, y, w, h);
                        ctx.clip();
                        ctx.drawImage(imgObj, x, y, w, h);
                        ctx.restore();
                    } else {
                        var colors = (this.params.rect_colors || "#e74c3c,#3498db,#2ecc71,#f39c12,#9b59b6").split(",");
                        ctx.fillStyle = colors[item.imgIdx % colors.length];
                        ctx.globalAlpha = 0.3;
                        ctx.fillRect(x, y, w, h);
                        ctx.globalAlpha = 1.0;
                    }

                    ctx.strokeStyle = "#333";
                    ctx.lineWidth = 1;
                    ctx.strokeRect(x, y, w, h);
                }

                currentY += row.height * scale + (r < rows.length - 1 ? gap * scale : 0);
            }

            ctx.fillStyle = "#333";
            ctx.font = "14px sans-serif";
            ctx.textAlign = "center";
            var totalM = totalLengthCm / 100;
            ctx.fillText(filmW + " cm  |  " + totalM.toFixed(2) + " m  |  " + (this.params.i18n.optimized_label || "Optimized"), ox + totalW / 2, oy + totalH + 20);
        },

        onAddToCart: function(e) {
            var data = this.$imagesData.val();
            if (!data || data === "[]" || data === "") {
                e.preventDefault();
                alert("Please upload at least one image before adding to cart.");
                return false;
            }
            return true;
        },

        escapeHtml: function(text) {
            var div = document.createElement("div");
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        }
    };

    $(document).ready(function() {
        if ($("#wcms-calculator").length) {
            calc.init();
        }
    });
})(jQuery);
