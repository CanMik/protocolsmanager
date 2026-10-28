<?php

use Dompdf\Dompdf;

/** Shared repeating footer for generated Protocols Manager PDFs. */
class PluginProtocolsmanagerPdf
{
	public static function addFooter(Dompdf $pdf, string $logo_path, string $document_type, string $immo_number, string $additional_text = ''): void
	{
		$logo_dimensions = null;
		if ($logo_path !== '' && is_file($logo_path) && is_readable($logo_path)) {
			$image = @getimagesize($logo_path);
			if (is_array($image) && in_array($image['mime'] ?? '', ['image/png', 'image/jpeg'], true)) {
				$logo_dimensions = [(int)$image[0], (int)$image[1]];
			}
		}

		$document_type = self::singleLine($document_type);
		$immo_number = self::singleLine($immo_number);
		$additional_text = self::singleLine($additional_text);
		$canvas = $pdf->getCanvas();
		$canvas->page_script(static function (int $page_number, int $page_count, $canvas, $font_metrics) use ($logo_path, $logo_dimensions, $document_type, $immo_number, $additional_text): void {
			$page_width = $canvas->get_width();
			$page_height = $canvas->get_height();
			$left = 34.0;
			$right = $page_width - 34.0;
			$footer_top = $page_height - 53.0;
			$font = $font_metrics->getFont('DejaVu Sans', 'normal');
			$bold_font = $font_metrics->getFont('DejaVu Sans', 'bold');
			$color = [0.35, 0.35, 0.35];
			$canvas->line($left, $footer_top, $right, $footer_top, [0.72, 0.72, 0.72], 0.5);

			if ($logo_dimensions !== null) {
				[$image_width, $image_height] = $logo_dimensions;
				$scale = min(42.0 / $image_width, 26.0 / $image_height);
				$draw_width = $image_width * $scale;
				$draw_height = $image_height * $scale;
				$canvas->image($logo_path, $left, $page_height - 46.0 + (26.0 - $draw_height) / 2, $draw_width, $draw_height);
			}

			$center_width = $page_width - 190.0;
			$type_size = 6.5;
			$name_size = 7.0;
			$note_size = 5.5;
			$type = self::fitText($document_type, $bold_font, $font_metrics, $type_size, $center_width);
			$immo = self::fitText($immo_number, $font, $font_metrics, $name_size, $center_width);
			$note = self::fitText($additional_text, $font, $font_metrics, $note_size, $center_width);
			self::drawCentered($canvas, $font_metrics, $type, $bold_font, $type_size, $color, $page_width / 2, $page_height - 43.0);
			self::drawCentered($canvas, $font_metrics, $immo, $font, $name_size, $color, $page_width / 2, $page_height - 34.0);
			if ($note !== '') {
				self::drawCentered($canvas, $font_metrics, $note, $font, $note_size, $color, $page_width / 2, $page_height - 24.0);
			}

			$page_label = sprintf(__('Page', 'protocolsmanager') . ' %d sur %d', $page_number, $page_count);
			$page_width_text = $font_metrics->getTextWidth($page_label, $font, 7.0);
			$canvas->text($right - $page_width_text, $page_height - 34.0, $page_label, $font, 7.0, $color);
		});
	}

	private static function singleLine(string $value): string
	{
		$value = preg_replace('/[\r\n\t\x00-\x1F]+/u', ' ', trim($value));
		return is_string($value) ? $value : '';
	}

	private static function fitText(string $text, $font, $font_metrics, float $size, float $max_width): string
	{
		if ($text === '' || $font_metrics->getTextWidth($text, $font, $size) <= $max_width) {
			return $text;
		}
		$ellipsis = '…';
		$length = mb_strlen($text, 'UTF-8');
		while ($length > 0) {
			$text = mb_substr($text, 0, $length, 'UTF-8') . $ellipsis;
			if ($font_metrics->getTextWidth($text, $font, $size) <= $max_width) {
				return $text;
			}
			$length--;
		}
		return $ellipsis;
	}

	private static function drawCentered($canvas, $font_metrics, string $text, $font, float $size, array $color, float $center_x, float $y): void
	{
		if ($text === '') {
			return;
		}
		$text_width = $font_metrics->getTextWidth($text, $font, $size);
		$canvas->text($center_x - ($text_width / 2), $y, $text, $font, $size, $color);
	}
}
