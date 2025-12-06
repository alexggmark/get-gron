<?php

namespace App\Jobs;

use App\Models\Scan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

class AnalyzeWebsite implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 300;

    protected Scan $scan;
    protected ?Crawler $crawler = null;
    protected ?string $html = null;

    public function __construct(Scan $scan)
    {
        $this->scan = $scan;
    }

    public function handle(): void
    {
        $currentStep = 'initializing';

        try {
            $this->scan->update(['status' => 'processing']);

            $currentStep = 'fetchUrl';
            $this->fetchUrl();

            $results = [];

            $currentStep = 'analyzeLighthouse';
            $results = [...$results, ...$this->analyzeLighthouse()];

            $currentStep = 'analyzeCtaElements';
            $results = [...$results, ...$this->analyzeCtaElements()];

            $currentStep = 'analyzeFormFriction';
            $results = [...$results, ...$this->analyzeFormFriction()];

            $currentStep = 'analyzeTrustSignals';
            $results = [...$results, ...$this->analyzeTrustSignals()];

            $currentStep = 'analyzeMobileViewport';
            $results = [...$results, ...$this->analyzeMobileViewport()];

            $currentStep = 'analyzeReadability';
            $results = [...$results, ...$this->analyzeReadability()];

            $currentStep = 'analyzeImageOptimization';
            $results = [...$results, ...$this->analyzeImageOptimization()];

            $currentStep = 'analyzeSchemaMarkup';
            $results = [...$results, ...$this->analyzeSchemaMarkup()];

            $currentStep = 'captureScreenshot';
            $results['screenshot_path'] = $this->captureScreenshot();

            $currentStep = 'saving results';
            $this->scan->update([
                ...$results,
                'status' => 'completed',
            ]);
        } catch (Throwable $e) {
            Log::error('Website analysis failed', [
                'scan_id' => $this->scan->id,
                'url' => $this->scan->url,
                'failed_step' => $currentStep,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->scan->update([
                'status' => 'failed',
                'failed_step' => $currentStep,
            ]);

            throw $e;
        }
    }

    protected function fetchUrl(): void
    {
        $response = Http::timeout(200)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ])
            ->get($this->scan->url);

        if (!$response->successful()) {
            throw new \RuntimeException("Failed to fetch URL: HTTP {$response->status()}");
        }

        $this->html = $response->body();
        $this->crawler = new Crawler($this->html);
    }

    protected function analyzeLighthouse(): array
    {
        try {
            $outputFile = storage_path('app/lighthouse-' . Str::uuid() . '.json');

            $lighthousePath = base_path('node_modules/.bin/lighthouse');

            $command = [
                $lighthousePath,
                $this->scan->url,
                '--output=json',
                '--output-path=' . $outputFile,
                '--chrome-flags=--headless --no-sandbox --disable-gpu --disable-dev-shm-usage',
                '--only-categories=performance,accessibility,seo',
                '--quiet',
            ];

            $result = Process::timeout(120)->run($command);

            if (!$result->successful() && !file_exists($outputFile)) {
                throw new \RuntimeException('Lighthouse CLI failed: ' . $result->errorOutput());
            }

            if (!file_exists($outputFile)) {
                throw new \RuntimeException('Lighthouse output file not created');
            }

            $jsonContent = file_get_contents($outputFile);
            $results = json_decode($jsonContent, true);

            @unlink($outputFile);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Failed to parse Lighthouse JSON output');
            }

            return [
                'lighthouse_performance' => $this->extractLighthouseScore($results, 'performance'),
                'lighthouse_accessibility' => $this->extractLighthouseScore($results, 'accessibility'),
                'lighthouse_seo' => $this->extractLighthouseScore($results, 'seo'),
            ];
        } catch (Throwable $e) {
            Log::warning('Lighthouse analysis failed', [
                'scan_id' => $this->scan->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'lighthouse_performance' => null,
                'lighthouse_accessibility' => null,
                'lighthouse_seo' => null,
            ];
        }
    }

    protected function extractLighthouseScore(array $results, string $category): ?int
    {
        // Lighthouse CLI outputs scores directly under categories
        $score = $results['categories'][$category]['score'] ?? null;

        return $score !== null ? (int) round($score * 100) : null;
    }

    protected function analyzeCtaElements(): array
    {
        $ctaPatterns = [
            'buy now', 'buy', 'shop now', 'shop', 'add to cart', 'add to bag',
            'get started', 'start now', 'sign up', 'subscribe', 'join now',
            'learn more', 'get quote', 'book now', 'reserve', 'order now',
            'download', 'try free', 'start free', 'claim', 'grab', 'get yours',
        ];

        $ctaDetails = [];
        $score = 100;

        $buttons = $this->crawler->filter('button, a.btn, a.button, [role="button"], input[type="submit"], .cta');

        $buttons->each(function (Crawler $node) use ($ctaPatterns, &$ctaDetails, &$score) {
            $text = strtolower(trim($node->text()));
            $nodeName = $node->nodeName();

            foreach ($ctaPatterns as $pattern) {
                if (str_contains($text, $pattern)) {
                    $cta = [
                        'text' => $node->text(),
                        'element' => $nodeName,
                        'issues' => [],
                    ];

                    // Check for href on links
                    if ($nodeName === 'a') {
                        $href = $node->attr('href');
                        if (empty($href) || $href === '#') {
                            $cta['issues'][] = 'Missing or invalid href';
                            $score -= 5;
                        }
                    }

                    // Check for accessible name
                    $ariaLabel = $node->attr('aria-label');
                    if (empty($text) && empty($ariaLabel)) {
                        $cta['issues'][] = 'Missing accessible name';
                        $score -= 10;
                    }

                    // Check for small text (basic heuristic)
                    $style = $node->attr('style') ?? '';
                    if (preg_match('/font-size:\s*(\d+)/', $style, $matches)) {
                        if ((int) $matches[1] < 14) {
                            $cta['issues'][] = 'Font size may be too small';
                            $score -= 5;
                        }
                    }

                    $ctaDetails[] = $cta;
                    break;
                }
            }
        });

        // Penalize if no CTAs found above the fold
        if (empty($ctaDetails)) {
            $score = 0;
        }

        return [
            'cta_score' => max(0, min(100, $score)),
            'cta_details' => $ctaDetails,
        ];
    }

    protected function analyzeFormFriction(): array
    {
        $formDetails = [];
        $totalScore = 100;
        $formCount = 0;

        $forms = $this->crawler->filter('form');

        $forms->each(function (Crawler $form) use (&$formDetails, &$totalScore, &$formCount) {
            $formCount++;
            $formData = [
                'action' => $form->attr('action'),
                'method' => $form->attr('method') ?? 'get',
                'inputs' => [],
                'issues' => [],
            ];

            $inputs = $form->filter('input:not([type="hidden"]):not([type="submit"]), textarea, select');
            $inputCount = $inputs->count();

            // Penalize forms with too many fields
            if ($inputCount > 5) {
                $formData['issues'][] = "High field count ({$inputCount} fields)";
                $totalScore -= ($inputCount - 5) * 3;
            }

            $inputs->each(function (Crawler $input) use (&$formData, &$totalScore) {
                $inputData = [
                    'name' => $input->attr('name'),
                    'type' => $input->attr('type') ?? $input->nodeName(),
                    'required' => $input->attr('required') !== null,
                    'issues' => [],
                ];

                // Check for label
                $id = $input->attr('id');
                $hasLabel = false;

                if ($id) {
                    try {
                        $label = $this->crawler->filter("label[for=\"{$id}\"]");
                        $hasLabel = $label->count() > 0;
                    } catch (Throwable) {
                        $hasLabel = false;
                    }
                }

                $placeholder = $input->attr('placeholder');
                $ariaLabel = $input->attr('aria-label');

                if (!$hasLabel && empty($placeholder) && empty($ariaLabel)) {
                    $inputData['issues'][] = 'Missing label or accessible name';
                    $totalScore -= 5;
                }

                // Check for autocomplete on common fields
                $name = strtolower($input->attr('name') ?? '');
                $autocomplete = $input->attr('autocomplete');

                $shouldHaveAutocomplete = ['email', 'name', 'phone', 'address', 'city', 'zip', 'postal'];
                foreach ($shouldHaveAutocomplete as $field) {
                    if (str_contains($name, $field) && empty($autocomplete)) {
                        $inputData['issues'][] = 'Missing autocomplete attribute';
                        $totalScore -= 3;
                        break;
                    }
                }

                $formData['inputs'][] = $inputData;
            });

            // Check for HTTPS action
            $action = $form->attr('action');
            if ($action && str_starts_with($action, 'http://')) {
                $formData['issues'][] = 'Form submits over insecure HTTP';
                $totalScore -= 20;
            }

            $formDetails[] = $formData;
        });

        return [
            'form_friction_score' => max(0, min(100, $totalScore)),
            'form_details' => $formDetails,
        ];
    }

    protected function analyzeTrustSignals(): array
    {
        $trustSignals = [];

        $trustPatterns = [
            'guarantee' => ['money back', 'guarantee', 'guaranteed', 'risk-free', 'risk free'],
            'security' => ['secure', 'ssl', 'encrypted', 'safe checkout', 'secure checkout', 'protected'],
            'reviews' => ['reviews', 'testimonials', 'rated', 'stars', 'customer feedback'],
            'certifications' => ['certified', 'accredited', 'approved', 'verified', 'trusted'],
            'shipping' => ['free shipping', 'fast delivery', 'free delivery', 'express shipping'],
            'support' => ['24/7', 'customer support', 'live chat', 'help center'],
        ];

        $bodyText = strtolower($this->crawler->filter('body')->text());

        foreach ($trustPatterns as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($bodyText, $pattern)) {
                    $trustSignals[] = [
                        'category' => $category,
                        'pattern' => $pattern,
                        'found' => true,
                    ];
                }
            }
        }

        // Check for trust badges/seals in images
        $trustBadgePatterns = ['trust', 'secure', 'badge', 'seal', 'certified', 'ssl', 'mcafee', 'norton', 'verisign', 'bbb'];
        $images = $this->crawler->filter('img');

        $images->each(function (Crawler $img) use ($trustBadgePatterns, &$trustSignals) {
            $src = strtolower($img->attr('src') ?? '');
            $alt = strtolower($img->attr('alt') ?? '');

            foreach ($trustBadgePatterns as $pattern) {
                if (str_contains($src, $pattern) || str_contains($alt, $pattern)) {
                    $trustSignals[] = [
                        'category' => 'badge',
                        'pattern' => $pattern,
                        'found' => true,
                        'element' => 'img',
                        'alt' => $img->attr('alt'),
                    ];
                    break;
                }
            }
        });

        // Check for review schema
        $hasReviewSchema = str_contains($this->html, '"@type":"Review"') ||
                          str_contains($this->html, '"@type": "Review"') ||
                          str_contains($this->html, "'@type':'Review'");

        if ($hasReviewSchema) {
            $trustSignals[] = [
                'category' => 'schema',
                'pattern' => 'Review schema detected',
                'found' => true,
            ];
        }

        return [
            'trust_signals' => $trustSignals,
        ];
    }

    protected function analyzeMobileViewport(): array
    {
        $mobileIssues = [];

        // Check viewport meta tag
        try {
            $viewport = $this->crawler->filter('meta[name="viewport"]');
            if ($viewport->count() === 0) {
                $mobileIssues[] = [
                    'type' => 'viewport',
                    'issue' => 'Missing viewport meta tag',
                    'severity' => 'high',
                ];
            } else {
                $content = $viewport->attr('content');
                if (!str_contains($content, 'width=device-width')) {
                    $mobileIssues[] = [
                        'type' => 'viewport',
                        'issue' => 'Viewport not set to device-width',
                        'severity' => 'medium',
                    ];
                }
            }
        } catch (Throwable) {
            $mobileIssues[] = [
                'type' => 'viewport',
                'issue' => 'Could not analyze viewport',
                'severity' => 'low',
            ];
        }

        // Check for horizontal scroll using Browsershot
        try {
            $browsershot = Browsershot::url($this->scan->url)
                ->windowSize(375, 812) // iPhone X dimensions
                ->userAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1')
                ->waitUntilNetworkIdle();

            $pageWidth = $browsershot->evaluate('document.documentElement.scrollWidth');
            $viewportWidth = 375;

            if ($pageWidth > $viewportWidth + 10) { // 10px tolerance
                $mobileIssues[] = [
                    'type' => 'horizontal_scroll',
                    'issue' => "Page width ({$pageWidth}px) exceeds mobile viewport ({$viewportWidth}px)",
                    'severity' => 'high',
                ];
            }
        } catch (Throwable $e) {
            Log::warning('Mobile viewport analysis failed', [
                'scan_id' => $this->scan->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Check for tap targets (buttons/links too small)
        $tappableElements = $this->crawler->filter('a, button, input[type="submit"], input[type="button"]');
        $smallTapTargets = 0;

        $tappableElements->each(function (Crawler $element) use (&$smallTapTargets) {
            $style = $element->attr('style') ?? '';

            // Basic heuristic: check inline styles for small dimensions
            if (preg_match('/(?:width|height):\s*(\d+)px/', $style, $matches)) {
                if ((int) $matches[1] < 44) { // Apple's minimum recommended tap target
                    $smallTapTargets++;
                }
            }
        });

        if ($smallTapTargets > 0) {
            $mobileIssues[] = [
                'type' => 'tap_targets',
                'issue' => "{$smallTapTargets} potentially small tap targets detected",
                'severity' => 'medium',
            ];
        }

        // Check for text size
        $this->crawler->filter('p, span, li, td')->each(function (Crawler $element) use (&$mobileIssues) {
            $style = $element->attr('style') ?? '';
            if (preg_match('/font-size:\s*(\d+)px/', $style, $matches)) {
                if ((int) $matches[1] < 12) {
                    static $smallTextWarned = false;
                    if (!$smallTextWarned) {
                        $mobileIssues[] = [
                            'type' => 'text_size',
                            'issue' => 'Some text may be too small on mobile',
                            'severity' => 'low',
                        ];
                        $smallTextWarned = true;
                    }
                }
            }
        });

        return [
            'mobile_issues' => $mobileIssues,
        ];
    }

    protected function analyzeReadability(): array
    {
        // Extract main content text
        $contentSelectors = ['main', 'article', '[role="main"]', '.content', '#content', '.post-content', '.entry-content'];
        $text = '';

        foreach ($contentSelectors as $selector) {
            try {
                $content = $this->crawler->filter($selector);
                if ($content->count() > 0) {
                    $text = $content->first()->text();
                    break;
                }
            } catch (Throwable) {
                continue;
            }
        }

        // Fallback to body if no main content found
        if (empty($text)) {
            try {
                $text = $this->crawler->filter('body')->text();
            } catch (Throwable) {
                return ['readability_score' => null];
            }
        }

        // Clean up text
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        if (strlen($text) < 100) {
            return ['readability_score' => null];
        }

        // Calculate Flesch Reading Ease score
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $sentenceCount = count($sentences);

        $words = str_word_count($text);

        // Estimate syllables (simplified)
        $syllables = $this->countSyllables($text);

        if ($sentenceCount === 0 || $words === 0) {
            return ['readability_score' => null];
        }

        // Flesch Reading Ease formula
        $avgWordsPerSentence = $words / $sentenceCount;
        $avgSyllablesPerWord = $syllables / $words;

        $fleschScore = 206.835 - (1.015 * $avgWordsPerSentence) - (84.6 * $avgSyllablesPerWord);

        // Normalize to 0-100
        $score = max(0, min(100, (int) round($fleschScore)));

        return [
            'readability_score' => $score,
        ];
    }

    protected function countSyllables(string $text): int
    {
        $words = str_word_count(strtolower($text), 1);
        $syllables = 0;

        foreach ($words as $word) {
            $syllables += $this->countWordSyllables($word);
        }

        return $syllables;
    }

    protected function countWordSyllables(string $word): int
    {
        $word = preg_replace('/[^a-z]/', '', strtolower($word));

        if (strlen($word) <= 3) {
            return 1;
        }

        // Remove silent e
        $word = preg_replace('/e$/', '', $word);

        // Count vowel groups
        preg_match_all('/[aeiouy]+/', $word, $matches);
        $count = count($matches[0]);

        return max(1, $count);
    }

    protected function analyzeImageOptimization(): array
    {
        $imageIssues = [];
        $images = $this->crawler->filter('img');

        $images->each(function (Crawler $img) use (&$imageIssues) {
            $src = $img->attr('src') ?? '';
            $alt = $img->attr('alt');
            $width = $img->attr('width');
            $height = $img->attr('height');
            $loading = $img->attr('loading');

            $issues = [];

            // Check for alt text
            if ($alt === null || $alt === '') {
                $issues[] = 'Missing alt attribute';
            }

            // Check for dimensions
            if (empty($width) || empty($height)) {
                $issues[] = 'Missing width/height attributes (causes layout shift)';
            }

            // Check for lazy loading on non-critical images
            if (empty($loading)) {
                $issues[] = 'Consider adding loading="lazy" for below-fold images';
            }

            // Check for modern formats
            $extension = strtolower(pathinfo(parse_url($src, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
            $legacyFormats = ['jpg', 'jpeg', 'png', 'gif'];
            $modernFormats = ['webp', 'avif'];

            if (in_array($extension, $legacyFormats)) {
                $issues[] = "Consider using modern format (WebP/AVIF) instead of {$extension}";
            }

            // Check for data URIs (often bloated)
            if (str_starts_with($src, 'data:')) {
                $dataSize = strlen($src);
                if ($dataSize > 10000) {
                    $issues[] = 'Large data URI detected - consider external file';
                }
            }

            if (!empty($issues)) {
                $imageIssues[] = [
                    'src' => Str::limit($src, 100),
                    'alt' => $alt,
                    'issues' => $issues,
                ];
            }
        });

        return [
            'image_issues' => $imageIssues,
        ];
    }

    protected function analyzeSchemaMarkup(): array
    {
        $schemas = [];

        // Look for JSON-LD schema
        $scripts = $this->crawler->filter('script[type="application/ld+json"]');

        $scripts->each(function (Crawler $script) use (&$schemas) {
            try {
                $content = $script->text();
                $data = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE && $data) {
                    $type = $data['@type'] ?? null;

                    // Handle @graph structure
                    if (isset($data['@graph']) && is_array($data['@graph'])) {
                        foreach ($data['@graph'] as $item) {
                            if (isset($item['@type'])) {
                                $schemas[] = [
                                    'type' => $item['@type'],
                                    'format' => 'JSON-LD',
                                    'valid' => true,
                                ];
                            }
                        }
                    } elseif ($type) {
                        $schemas[] = [
                            'type' => $type,
                            'format' => 'JSON-LD',
                            'valid' => true,
                        ];
                    }
                }
            } catch (Throwable) {
                // Invalid JSON, skip
            }
        });

        // Look for microdata
        $microdataItems = $this->crawler->filter('[itemtype]');

        $microdataItems->each(function (Crawler $item) use (&$schemas) {
            $itemtype = $item->attr('itemtype');
            if ($itemtype) {
                // Extract type from schema.org URL
                $type = basename(parse_url($itemtype, PHP_URL_PATH) ?? '');
                if ($type) {
                    $schemas[] = [
                        'type' => $type,
                        'format' => 'Microdata',
                        'valid' => true,
                    ];
                }
            }
        });

        // Check for important missing schemas
        $foundTypes = array_column($schemas, 'type');
        $recommendedTypes = ['Organization', 'WebSite', 'Product', 'LocalBusiness', 'BreadcrumbList'];
        $missingRecommended = [];

        foreach ($recommendedTypes as $type) {
            if (!in_array($type, $foundTypes)) {
                $missingRecommended[] = $type;
            }
        }

        if (!empty($missingRecommended)) {
            $schemas[] = [
                'type' => 'recommendation',
                'message' => 'Consider adding: ' . implode(', ', array_slice($missingRecommended, 0, 3)),
                'missing' => $missingRecommended,
            ];
        }

        return [
            'schema_detected' => $schemas,
        ];
    }

    protected function captureScreenshot(): ?string
    {
        try {
            $filename = 'screenshots/' . Str::uuid() . '.png';

            Storage::disk('public')->makeDirectory('screenshots');

            $path = Storage::disk('public')->path($filename);

            Browsershot::url($this->scan->url)
                ->windowSize(1920, 1080)
                ->fullPage()
                ->waitUntilNetworkIdle()
                ->save($path);

            return $filename;
        } catch (Throwable $e) {
            Log::warning('Screenshot capture failed', [
                'scan_id' => $this->scan->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('AnalyzeWebsite job failed permanently', [
            'scan_id' => $this->scan->id,
            'url' => $this->scan->url,
            'error' => $exception->getMessage(),
        ]);

        // Only update if not already marked as failed (to preserve failed_step from catch block)
        if ($this->scan->status !== 'failed') {
            $this->scan->update([
                'status' => 'failed',
                'failed_step' => $this->scan->failed_step ?? 'unknown',
            ]);
        }
    }
}
