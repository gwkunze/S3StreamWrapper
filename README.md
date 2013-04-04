# S3StreamWrapper #

A simple stream wrapper for Amazon S3.

Example
=======

``` php
<?php

use S3StreamWrapper\S3StreamWrapper;

S3StreamWrapper::register();

$options = array(
    'key' => "YOUR AWS KEY HERE",
    'secret' => "YOUR AWS SECRET HERE",
    'region' => 'us-east-1',
);

stream_context_set_default(array('s3' => $options));

echo file_get_contents("s3://mybucket/file1");

echo file_put_contents("s3://mybucket/file2", "Foobar!");

print_r(scandir("s3://mybucket/"));
```

License
=======

MIT, See LICENSE
