<?php namespace nocio\FormStore\Widgets;

use Backend\Classes\FormWidgetBase;
use nocio\FormStore\Traits\ManagesUploads;

use October\Rain\Html\Helper as HtmlHelper;

class FrontendFileUpload extends FormWidgetBase
{
    
    use ManagesUploads;
    
    //
    // Configurable properties
    //
    
    /**
     *
     * @var type 
     */
    public $alias = null;

    /**
     *
     * @var type 
     */
    public $mode = null;
    
    /**
     * @var string Prompt text to display for the upload button.
     */
    public $prompt = null;

    /**
     * @var int Preview image width
     */
    public $imageWidth = null;

    /**
     * @var int Preview image height
     */
    public $imageHeight = null;

    /**
     * @var mixed Collection of acceptable file types.
     */
    public $fileTypes = false;

    /**
     * @var mixed Collection of acceptable mime types.
     */
    public $mimeTypes = false;

    /**
     * @var array Options used for generating thumbnails.
     */
    public $thumbOptions = [
        'mode'      => 'crop',
        'extension' => 'auto'
    ];

    /**
     * @var boolean Allow the user to set a caption.
     */
    public $useCaption = true;
    
    /**
     * Render mode
     * @var boolean
     */
    public $previewMode = false;

    /**
     * @var string A unique alias to identify this widget.
     */
    protected $defaultAlias = 'frontendfileupload';
    
    
    public function init()
    {
        $this->fillFromConfig([
            'mode',
            'prompt',
            'imageWidth',
            'imageHeight',
            'fileTypes',
            'mimeTypes',
            'thumbOptions',
            'placeholder'
        ]);
    }
    
    public function getEventHandler($name) {
        $fix = studly_case(HtmlHelper::nameToId($this->fieldName));
        return str_replace($fix, '', $this->alias) . '::' . $name;
    }
    
    public function render() {
        // @todo: preview mode
        
        $this->vars['id'] = $this->getId();
        $this->vars['name'] = $this->getFieldName();
        $this->vars['fieldName'] = $this->fieldName;
        $this->vars['prompt'] = empty($this->prompt) ? 'Browse' : $this->prompt;
        $this->vars['fileTypes'] = $this->fileTypes;
        $this->vars['fileList'] = $this->getFileList();
        $this->vars['isPopulated'] = $this->isPopulated();
        $this->vars['mode'] = ($this->mode == 'image') ? 'image' : 'file';
        $this->vars['placeholder'] = empty($this->placeholder) ? '' : $this->placeholder;
        
        $relationType = $this->model->getRelationType($this->fieldName);
        $this->isMulti = $this->vars['isMulti'] = ($relationType == 'attachMany' || $relationType == 'morphMany');
        
        return $this->makePartial('frontendfileupload');
    }

}