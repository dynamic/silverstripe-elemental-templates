<?php

namespace Dynamic\ElementalTemplates\Controller;

use Dynamic\ElementalTemplates\Models\Template;
use SilverStripe\Assets\Image;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Security;
use SilverStripe\Security\SecurityToken;

/**
 * Controller for handling screenshot uploads from the CMS.
 * Receives base64-encoded image data and saves it as the template's LayoutImage.
 */
class ScreenshotUploadController extends Controller
{
    private static $url_segment = 'template-screenshot-upload';

    private static $url_handlers = [
        'upload' => 'upload',
        '' => 'index',
    ];

    private static $allowed_actions = [
        'upload',
        'index',
    ];

    /**
     * Default action - returns error for direct access.
     *
     * @return HTTPResponse
     */
    public function index(): HTTPResponse
    {
        return $this->jsonResponse(['error' => 'POST to /upload required'], 400);
    }

    /**
     * Handle screenshot upload from CMS.
     * Expects POST with:
     * - templateID: int
     * - screenshot: base64 encoded image data
     * - SecurityID: CSRF token
     *
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function upload(HTTPRequest $request): HTTPResponse
    {
        // Check for logged in user
        $member = Security::getCurrentUser();
        if (!$member) {
            return $this->jsonResponse(['error' => 'Not authenticated'], 401);
        }

        // Verify CSRF token
        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->jsonResponse(['error' => 'Invalid security token'], 403);
        }

        // Only accept POST
        if (!$request->isPOST()) {
            return $this->jsonResponse(['error' => 'POST required'], 405);
        }

        $templateID = (int) $request->postVar('templateID');
        $screenshotData = $request->postVar('screenshot');

        if (!$templateID || !$screenshotData) {
            return $this->jsonResponse(['error' => 'Missing required parameters'], 400);
        }

        // Find template
        $template = Template::get()->byID($templateID);
        if (!$template) {
            return $this->jsonResponse(['error' => 'Template not found'], 404);
        }

        // Check edit permission
        if (!$template->canEdit($member)) {
            return $this->jsonResponse(['error' => 'Permission denied'], 403);
        }

        try {
            // Decode base64 image
            $imageData = $this->decodeBase64Image($screenshotData);
            if (!$imageData) {
                return $this->jsonResponse(['error' => 'Invalid image data'], 400);
            }

            // Save the image
            $image = $this->saveScreenshot($template, $imageData);

            // Update template's LayoutImage
            $template->LayoutImageID = $image->ID;
            $template->write();

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Screenshot saved successfully',
                'imageID' => $image->ID,
                'imageURL' => $image->getURL(),
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse(['error' => 'Failed to save screenshot: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Decode base64 image data.
     *
     * @param string $base64Data
     * @return array|null ['mime' => string, 'data' => binary]
     */
    protected function decodeBase64Image(string $base64Data): ?array
    {
        // Remove data URL prefix if present
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
            $declaredExtension = $matches[1];
            $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
        } else {
            $declaredExtension = 'png';
        }

        $data = base64_decode($base64Data);
        if ($data === false) {
            return null;
        }

        // Validate that the decoded data is actually a valid image
        $imageInfo = @getimagesizefromstring($data);
        if ($imageInfo === false) {
            return null;
        }

        // Map IMAGETYPE constants to extensions
        $typeToExtension = [
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_GIF => 'gif',
            IMAGETYPE_WEBP => 'webp',
        ];

        $actualExtension = $typeToExtension[$imageInfo[2]] ?? null;
        if (!$actualExtension) {
            return null; // Unsupported image type
        }

        // Normalize declared extension
        $normalizedDeclared = ($declaredExtension === 'jpeg') ? 'jpg' : $declaredExtension;

        // Ensure actual image type matches declared extension
        if ($actualExtension !== $normalizedDeclared) {
            return null;
        }

        $mimeTypes = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];

        return [
            'extension' => $actualExtension,
            'mime' => $mimeTypes[$actualExtension] ?? 'image/png',
            'data' => $data,
        ];
    }

    /**
     * Save screenshot as an Image asset.
     *
     * @param Template $template
     * @param array $imageData
     * @return Image
     */
    protected function saveScreenshot(Template $template, array $imageData): Image
    {
        // Use uniqid for better filename uniqueness than time()
        $filename = 'template-preview-' . $template->ID . '-' . uniqid('', true) . '.' . $imageData['extension'];
        $folderPath = 'Uploads/template-screenshots';

        // Create temp file
        $tempPath = TEMP_PATH . '/' . $filename;

        try {
            file_put_contents($tempPath, $imageData['data']);

            // Create new image (always create new to avoid issues with existing)
            $image = Image::create();
            $image->setFromLocalFile($tempPath, $folderPath . '/' . $filename);
            $image->write();

            // Publish the image
            $image->publishSingle();

            return $image;
        } finally {
            // Clean up temp file
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    /**
     * Return JSON response.
     *
     * @param array $data
     * @param int $code
     * @return HTTPResponse
     */
    protected function jsonResponse(array $data, int $code = 200): HTTPResponse
    {
        $response = HTTPResponse::create(json_encode($data));
        $response->addHeader('Content-Type', 'application/json');
        $response->setStatusCode($code);
        return $response;
    }
}
