<?php
namespace APP\plugins\generic\publishToFacebook\formRequests;
use APP\plugins\generic\publishToFacebook\classes\Constants;
use Illuminate\Foundation\Http\FormRequest;
class EditSettingsRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            Constants::PAGE_ID => 'required|string|max:255',
            Constants::ACCESS_TOKEN => 'required|string|max:255',
            Constants::MESSAGE_FORMAT_ARTICLE => 'nullable|string|max:1000',
            Constants::MESSAGE_FORMAT_ISSUE => 'nullable|string|max:1000',
            Constants::AUTO_PUBLISH_ARTICLES => 'nullable|boolean',
            Constants::AUTO_PUBLISH_ISSUES => 'nullable|boolean',
        ];
    }
}
