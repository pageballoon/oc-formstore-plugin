<?php namespace Nocio\FormStore\Traits;

use Input;
use Request;
use Response;
use File;
use Validator;

// Returns a file size limit in bytes based on the PHP upload_max_filesize
// and post_max_size
function file_upload_max_size() {
    static $max_size = -1;

    if ($max_size < 0) {
        // Start with post_max_size.
        $post_max_size = parse_size(ini_get('post_max_size'));
        if ($post_max_size > 0) {
            $max_size = $post_max_size;
        }

        // If upload_max_size is less, then reduce. Except if upload_max_size is
        // zero, which indicates no limit.
        $upload_max = parse_size(ini_get('upload_max_filesize'));
        if ($upload_max > 0 && $upload_max < $max_size) {
            $max_size = $upload_max;
        }
    }
    return $max_size;
}

function parse_size($size) {
    $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
    $size = preg_replace('/[^0-9\.]/', '', $size); // Remove the non-numeric characters from the size.
    if ($unit) {
        // Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
        return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
    }
    else {
        return round($size);
    }
}

function human_filesize($bytes, $dec = 2)
{
    $size   = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    $factor = floor((strlen($bytes) - 1) / 3);

    return sprintf("%.{$dec}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}


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
    
    private function validateUpload() {
        $validationRules = ['max:' . (string) file_upload_max_size()];
        
        //if ($fileTypes = $this->processFileTypes()) {
        //    $validationRules[] = 'extensions:' . $fileTypes;
        //}

        $validation = Validator::make(Request::all(), [
            'file_data' => $validationRules
        ]);

        if ($validation->fails()) {
            throw new \Exception($validation->messages()->first('file_data'));
        }
    }
    
    /**
     * Process uploaded files
     * @return mixed
     */
    protected function processUploads() {
        if (! Request::header('X-OCTOBER-FILEUPLOAD')) {
            return false;
        }

        try {
            $uploadedFile = Input::file('file_data');

            if ( ! Input::hasFile('file_data')) {
                $max_upload = human_filesize(file_upload_max_size(), 1);
                throw new \Exception('File exceeds file upload limit of ' . $max_upload);
            }

            if (!$uploadedFile->isValid()) {
                throw new \Exception(sprintf('File %s is not valid.', $uploadedFile->getClientOriginalName()));
            }

            $model_field = Request::header('X-OCTOBER-FILEUPLOAD');
            if (! $this->model->hasRelation($model_field)) {
                throw new \Exception('Invalid field');
            }
            
            // $this->validateUpload();
            
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
        catch (\Exception $ex) {
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
