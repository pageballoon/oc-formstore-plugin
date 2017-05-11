<?php namespace nocio\FormStore\Traits;

use Input;
use Request;
use Response;
use File;

trait ManagesUploads {
    
    public $fileList = false;

    /**
     * Returns the specified accepted file types, or the default
     * based on the mode. Image mode will return:
     * - jpg,jpeg,bmp,png,gif,svg
     * @return string
     */
    protected function processFileTypes($includeDot = false)
    {
        $types = $this->property('fileTypes', '*');

        if (!$types || $types == '*') {
            $types = implode(',', Definitions::get('defaultExtensions'));
        }

        if (!is_array($types)) {
            $types = explode(',', $types);
        }

        $types = array_map(function($value) use ($includeDot) {
            $value = trim($value);

            if (substr($value, 0, 1) == '.') {
                $value = substr($value, 1);
            }

            if ($includeDot) {
                $value = '.'.$value;
            }

            return $value;
        }, $types);

        return implode(',', $types);
    }
    
    private function validateUpload($uploadedFile) {
        $validationRules = ['max:' . File::getMaxFilesize()];
        
        //if ($fileTypes = $this->processFileTypes()) {
        //    $validationRules[] = 'extensions:' . $fileTypes;
        //}

        $validation = Validator::make(
            ['file_data' => $uploadedFile],
            ['file_data' => $validationRules]
        );

        if ($validation->fails()) {
            throw new ValidationException($validation);
        }

        if (!$uploadedFile->isValid()) {
            throw new \Exception(sprintf('File %s is not valid.', $uploadedFile->getClientOriginalName()));
        }
    }
    
    /**
     * Process uploaded files
     * @return mixed
     */
    protected function processUploads() {
        
        if ( ! Input::hasFile('file_data')) {
            return false;
        }
        
        try {
            $uploadedFile = Input::file('file_data');
            $model_field = Request::header('X-OCTOBER-FILEUPLOAD');

            if (! $this->model->hasRelation($model_field)) {
                throw new \Exception('Invalid field');
            }
            
            //$this->validateUpload($uploadedFile);
            
            $fileModel = $this->model->getRelationDefinition($model_field)[0];
            
            $file = new $fileModel();
            $file->data = $uploadedFile;
            $file->is_public = true;
            $file->save();

            $this->model->{$model_field}()->add($file);

            //$file = $this->decorateFileAttributes($file);

            $result = [
                'id' => $file->id,
                //'thumb' => $file->thumbUrl,
                //'path' => $file->pathUrl
            ];

            return Response::json($result, 200);
        }
        catch (Exception $ex) {
            return Response::json($ex->getMessage(), 400);
        }
    }
    
    /**
     * Removes attachment
     */
    public function onRemoveAttachment()
    {   
        $model_field = post('field');
        $file_id = post('file_id');
        
        $fileModel = $this->model->getRelationDefinition($model_field)[0];
        
        if (($file_id) && ($file = $fileModel::find($file_id))) {
            $this->model->{$model_field}()->remove($file);
        }
    }
    
    public function getFileList()
    {
        if ($this->fileList) {
            return $this->fileList;
        }
        
        $this->fileList = $this->model->{$this->fieldName}()
                ->orderBy('id', 'desc')
                ->get();
        
        if ( ! $this->fileList) {
            $this->fileList = new Collection;
        }

        /*
         * Decorate each file with thumb
         */
        $this->fileList->each(function($file) {
            $this->decorateFileAttributes($file);
        });

        return $this->fileList;
    }
    
    /**
     * Adds the bespoke attributes used internally by this widget.
     * - thumbUrl
     * - pathUrl
     * @return System\Models\File
     */
    protected function decorateFileAttributes($file)
    {
        $path = $thumb = $file->getPath();

        if ($this->mode == 'image' || $file->isImage()) {
            if (!empty($this->imageWidth) || !empty($this->imageHeight)) {
                $thumb = $file->getThumb($this->imageWidth, $this->imageHeight, $this->thumbOptions);
            }
            else {
                $thumb = $file->getThumb(63, 63, $this->thumbOptions);
            } 
        }
        
        $file->pathUrl = $path;
        $file->thumbUrl = $thumb;
        
        return $file;
    }

    public function isPopulated()
    {
        if ( ! $this->getFileList()) {
            return false;
        }

        return $this->fileList->count() > 0;
    }
    
    
    public function getCssBlockDimensions()
    {
        return $this->getCssDimensions('block');
    }

    /**
     * Returns the CSS dimensions for the uploaded image,
     * uses auto where no dimension is provided.
     * @param string $mode
     * @return string
     */
    public function getCssDimensions($mode = null)
    {
        if (!$this->imageWidth && !$this->imageHeight) {
            return '';
        }

        $cssDimensions = '';

        if ($mode == 'block') {
            $cssDimensions .= ($this->imageWidth)
                ? 'width: '.$this->imageWidth.'px;'
                : 'width: '.$this->imageHeight.'px;';

            $cssDimensions .= ($this->imageHeight)
                ? 'height: '.$this->imageHeight.'px;'
                : 'height: auto;';
        }
        else {
            $cssDimensions .= ($this->imageWidth)
                ? 'width: '.$this->imageWidth.'px;'
                : 'width: auto;';

            $cssDimensions .= ($this->imageHeight)
                ? 'height: '.$this->imageHeight.'px;'
                : 'height: auto;';
        }

        return $cssDimensions;
    }
    
}
