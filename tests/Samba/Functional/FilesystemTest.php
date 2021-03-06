<?php

namespace Samba\Functional;

class FilesystemTest extends FunctionalTestCase
{
    public function testMkDir()
    {
        $url = self::$shareUrl . '/test-dir';
        $result = mkdir($url);
        static::assertTrue($result);

        $localPath = self::$sharePath . '/test-dir';

        static::assertFileExists($localPath);
        static::assertIsDir($localPath);
    }

    public function testRmDir()
    {
        $localPath = self::$sharePath . '/test-dir';
        mkdir($localPath);

        static::assertFileExists($localPath);
        static::assertIsDir($localPath);

        $url = self::$shareUrl . '/test-dir';
        $result = rmdir($url);
        static::assertTrue($result);
        static::assertFileNotExists($localPath);
        static::assertFileNotExists($url);
    }

    /**
     * @dataProvider statNotExistsProvider
     * @expectedException \Samba\SambaException
     *
     * @param string $url
     */
    public function testStatNotExists($url)
    {
        $url = $this->urlSub($url);

        static::assertFileNotExists($url);
        stat($url);
    }

    /**
     * @return array
     */
    public function statNotExistsProvider()
    {
        return array(
            'host' => array('smb://not-found'),
            'share' => array('smb://{hostname}/share-test-not-found'),
            'path' => array('smb://{hostname}/{share}/file-not-found'),
        );
    }

    /**
     * @dataProvider pathStatProvider
     * @param string $path
     */
    public function testPathStat($path)
    {
        file_put_contents(self::$sharePath . '/first.nfo', 'first');
        mkdir(self::$sharePath . '/first-stat');
        file_put_contents(self::$sharePath . '/first-stat/second.nfo', 'second');
        mkdir(self::$sharePath . '/first-stat/second-stat');
        file_put_contents(self::$sharePath . '/first-stat/second-stat/third.nfo', 'third');

        touch(self::$sharePath . $path, 1403344333);
        $url = self::$shareUrl . '/' . $path;

        $smbStat = stat($url);
        static::assertInternalType('array', $smbStat);
        static::assertArrayHasKey('mtime', $smbStat);
        static::assertSame(1403344333, $smbStat['mtime']);
    }

    /**
     * @return array
     */
    public function pathStatProvider()
    {
        return array(
            'file first level' => array('/first.nfo', ),
            'folder first level' => array('/first-stat'),
            'file second level' => array('/first-stat/second.nfo'),
            'folder second level' => array('/first-stat/second-stat'),
            'file third level' => array('/first-stat/second-stat/third.nfo'),
        );
    }

    public function testHostStat()
    {
        $stat = stat(self::$hostUrl);
        static::assertStat($stat);
    }

    public function testShareStat()
    {
        $stat = stat(self::$shareUrl);
        static::assertStat($stat);
    }

    public function testDir()
    {
        $dirPath = self::$sharePath . '/dir-test';

        mkdir($dirPath);
        file_put_contents($dirPath . '/one', 'content');
        file_put_contents($dirPath . '/second.txt', 'more content');
        mkdir($dirPath . '/sub-dir');

        $dh = opendir(self::$shareUrl . '/dir-test');

        static::assertResource($dh);

        $files = array();
        while (false !== ($file = readdir($dh))) {
            $files[] = $file;
        }

        $expectedFiles = array('one', 'second.txt', 'sub-dir');
        static::assertArrayEquals($expectedFiles, $files);

        static::assertFalse(readdir($dh));

        rewinddir($dh);

        static::assertNotFalse(readdir($dh));

        closedir($dh);
    }

    public function testDirShare()
    {
        file_put_contents(self::$sharePath . '/one', 'content');
        file_put_contents(self::$sharePath . '/second.txt', 'more content');
        mkdir(self::$sharePath . '/sub-dir');

        $dh = opendir(self::$shareUrl);

        static::assertResource($dh);

        $files = array();
        while (false !== ($file = readdir($dh))) {
            $files[] = $file;
        }

        $expectedFiles = array('one', 'second.txt', 'sub-dir');
        static::assertArrayEquals($expectedFiles, $files);
    }

    public function testDirEmpty()
    {
        $dh = opendir(self::$shareUrl);

        static::assertResource($dh);

        static::assertFalse(readdir($dh));
    }

    public function testDirHost()
    {
        $dh = opendir(self::$hostUrl);
        $files = array();
        while (false !== ($file = readdir($dh))) {
            $files[] = $file;
        }

        static::assertContains(self::$share, $files);
    }

    /**
     * @expectedException \Samba\SambaException
     * @expectedExceptionMessage NT_STATUS_OBJECT_PATH_NOT_FOUND listing
     */
    public function testDirNotExists()
    {
        opendir(self::$shareUrl . '/not-found-dir');
    }

    /**
     * @expectedException \Samba\SambaException
     * @expectedExceptionMessage dir_opendir(): error in URL
     */
    public function testDirInvalidUrl()
    {
        opendir('smb://');
    }

    public function testUnlink()
    {
        file_put_contents(self::$sharePath . '/test-file.txt', 'content');

        $fileUrl = self::$shareUrl . '/test-file.txt';

        static::assertFileExists($fileUrl);
        static::assertIsFile($fileUrl);
        static::assertNotDir($fileUrl);

        $result = unlink($fileUrl);
        static::assertTrue($result);

        clearstatcache();
        static::assertFileNotExists($fileUrl);
    }

    /**
     * @expectedException \Samba\SambaException
     * @expectedExceptionMessage NT_STATUS_NO_SUCH_FILE listing
     */
    public function testUnlinkDir()
    {
        mkdir(self::$sharePath . '/test-dir');

        $dirUrl = self::$shareUrl . '/test-dir';

        static::assertFileExists($dirUrl);
        static::assertNotFile($dirUrl);
        static::assertIsDir($dirUrl);

        unlink($dirUrl);
    }

    /**
     * @expectedException \Samba\SambaException
     * @expectedExceptionMessage del: error - URL should be path
     */
    public function testUnlinkShare()
    {
        unlink(self::$shareUrl);
    }

    /**
     * @expectedException \Samba\SambaException
     * @expectedExceptionMessage del: error - URL should be path
     */
    public function testUnlinkHost()
    {
        unlink(self::$hostUrl);
    }

    /**
     * @expectedException \Samba\SambaException
     * @expectedExceptionMessage del: error - URL should be path
     */
    public function testUnlinkInvalidUrl()
    {
        unlink('smb://');
    }

    public function testRename()
    {
        mkdir(self::$sharePath . '/old');

        $result = rename(self::$shareUrl . '/old', self::$shareUrl . '/new');
        static::assertTrue($result);

        clearstatcache();
        static::assertFileNotExists(self::$sharePath . '/old');
        static::assertFileExists(self::$sharePath . '/new');
    }

    /**
     * @expectedException \Samba\SambaException
     * @expectedExceptionMessage FROM & TO must be in same server-share-user-pass-domain
     * @dataProvider renameDiffHostShare
     * @param string $from
     * @param string $to
     */
    public function testRenameDiffHostShare($from, $to)
    {
        $from = $this->urlSub($from);
        $to = $this->urlSub($to);
        rename($from, $to);
    }

    /**
     * @return array
     */
    public function renameDiffHostShare()
    {
        return array(
            'host' => array(
                '{shareUrl}/new',
                'smb://host/share/old'
            ),
            'share' => array(
                '{shareUrl}/new',
                '{hostUrl}/another-share/old'
            ),
        );
    }

    /**
     * @expectedException \Samba\SambaException
     * @expectedExceptionMessage rename: error - URL should be path
     * @dataProvider renameInvalidUrl
     * @param string $from
     * @param string $to
     */
    public function testRenameInvalidUrl($from, $to)
    {
        $from = $this->urlSub($from);
        $to = $this->urlSub($to);
        rename($from, $to);
    }

    /**
     * @return array
     */
    public function renameInvalidUrl()
    {
        return array(
            'path - share' => array(
                '{shareUrl}/dir',
                '{shareUrl}',
            ),
            'path - host' => array(
                '{shareUrl}/dir',
                '{hostUrl}',
            ),
            'path - invalid' => array(
                '{shareUrl}/dir',
                'smb://',
            ),
            'share - path' => array(
                '{shareUrl}',
                '{shareUrl}/dir',
            ),
            'host - path' => array(
                '{hostUrl}',
                '{shareUrl}/dir',
            ),
            'invalid - path' => array(
                'smb://',
                '{shareUrl}/dir',
            ),
        );
    }

    /**
     * @expectedException \Samba\SambaException
     * @expectedExceptionMessage NT_STATUS_OBJECT_NAME_NOT_FOUND renaming files \old -> \new
     */
    public function testRenameNotFound()
    {
        rename(self::$shareUrl . '/old', self::$shareUrl . '/new');
    }
}
