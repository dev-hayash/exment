<?php
namespace Exceedone\Exment\Console;

use Exceedone\Exment\Model\Define;
use Exceedone\Exment\Storage\Disk\BackupDiskService;
use Illuminate\Support\Facades\Storage;

trait BackupRestoreTrait
{
    protected $diskService;

    protected function disk()
    {
        return $this->diskService->diskItem()->disk();
    }

    protected function tmpDisk()
    {
        return $this->diskService->tmpDiskItem()->disk();
    }

    protected function initBackupRestore($basename = null)
    {
        $this->diskService = new BackupDiskService($basename);
    }
}
