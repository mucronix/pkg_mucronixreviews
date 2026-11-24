<?php

/**
 * A mail server of the stand that delivers nowhere: every message it is given becomes a file in a folder, one after
 * another (1.eml, 2.eml...). Listens on 127.0.0.1 only. With a file "refuse" in the folder it refuses every recipient,
 * as a broken server would. Not part of the package.
 *
 * Usage: php smtp-sink.php <folder> <port>
 */

$folder = rtrim($argv[1] ?? exit("the folder\n"), '/\\');
$port   = (int) ($argv[2] ?? 2525);
$server = stream_socket_server("tcp://127.0.0.1:$port", $code, $error) ?: exit("$error\n");

@mkdir($folder, 0777, true);

while ($client = @stream_socket_accept($server, -1)) {
    stream_set_timeout($client, 30);
    fwrite($client, "220 stand ESMTP\r\n");

    $data = null;

    while (($line = fgets($client)) !== false) {
        if ($data !== null) {
            if (rtrim($line, "\r\n") === '.') {
                $number = count(glob("$folder/*.eml")) + 1;
                file_put_contents("$folder/$number.eml", $data);
                $data = null;
                fwrite($client, "250 kept as $number\r\n");

                continue;
            }

            // A dot at the start of a line is doubled on the wire
            $data .= str_starts_with($line, '..') ? substr($line, 1) : $line;

            continue;
        }

        $verb = strtoupper(substr(trim($line), 0, 4));

        // The process lives long: PHP would remember whether "refuse" was there
        clearstatcache();

        if ($verb === 'EHLO' || $verb === 'HELO') {
            fwrite($client, "250 stand\r\n");
        } elseif ($verb === 'RCPT' && is_file("$folder/refuse")) {
            fwrite($client, "554 refused by the stand\r\n");
        } elseif ($verb === 'DATA') {
            $data = '';
            fwrite($client, "354 go on\r\n");
        } elseif ($verb === 'QUIT') {
            fwrite($client, "221 bye\r\n");
            break;
        } else {
            fwrite($client, "250 ok\r\n");
        }
    }

    fclose($client);
}
