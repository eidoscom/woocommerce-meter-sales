jQuery(function($) {
    "use strict";

    var $addBtn = $(".wcms_add_tier");
    var $tableBody = $(".wcms_tiers_table tbody");
    var index = $tableBody.find("tr").length;

    $addBtn.on("click", function() {
        var row = "<tr>" +
            "<td><input type=\"number\" step=\"0.01\" min=\"0\" name=\"wcms_tiers[" + index + "][from]\" value=\"\" style=\"width:80px;\" /></td>" +
            "<td><input type=\"number\" step=\"0.01\" min=\"0\" name=\"wcms_tiers[" + index + "][to]\" value=\"\" style=\"width:80px;\" /></td>" +
            "<td><input type=\"number\" step=\"0.01\" min=\"0\" name=\"wcms_tiers[" + index + "][price]\" value=\"\" style=\"width:100px;\" /></td>" +
            "<td><button type=\"button\" class=\"button wcms_remove_tier\">-</button></td>" +
        "</tr>";
        $tableBody.append(row);
        index++;
    });

    $tableBody.on("click", ".wcms_remove_tier", function() {
        $(this).closest("tr").remove();
    });

    var $checkbox = $("#_wcms_enabled");
    var $fields = $("#wcms_fields");

    $checkbox.on("change", function() {
        if ($(this).is(":checked")) {
            $fields.slideDown();
        } else {
            $fields.slideUp();
        }
    });

    function updateRectColorsInput($wrapper) {
        var colors = [];
        $wrapper.find(".wcms-rect-color-swatch").each(function() {
            colors.push($(this).data("color"));
        });
        $wrapper.find(".wcms-rect-colors-input").val(colors.join(","));
    }

    $(document).on("click", ".wcms-rect-color-add-btn", function() {
        var $wrapper = $(this).closest(".wcms-rect-colors-wrapper");
        var $picker = $wrapper.find(".wcms-rect-color-picker");
        var color = $picker.val();
        if (!color || color.length < 4) return;
        if (color.indexOf("#") !== 0) color = "#" + color;
        if (!/^#[0-9a-f]{6}$/i.test(color)) return;
        $wrapper.find(".wcms-rect-colors-list").append(
            '<span class="wcms-rect-color-swatch" data-color="' + color + '" style="background:' + color + ';">' +
            '<button type="button" class="wcms-rect-color-remove">&times;</button></span>'
        );
        updateRectColorsInput($wrapper);
        $picker.iris("color", "#3498db");
    });

    $(document).on("click", ".wcms-rect-color-remove", function() {
        var $wrapper = $(this).closest(".wcms-rect-colors-wrapper");
        $(this).closest(".wcms-rect-color-swatch").remove();
        updateRectColorsInput($wrapper);
    });

    $(".wcms-rect-color-picker").wpColorPicker({
        hide: true,
        palettes: ["#e74c3c","#3498db","#2ecc71","#f39c12","#9b59b6","#1abc9c","#34495e","#e67e22"]
    });
});
