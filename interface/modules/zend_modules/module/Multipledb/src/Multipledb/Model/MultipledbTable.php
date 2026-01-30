<?php

/* +-----------------------------------------------------------------------------+
* Copyright 2016 matrix israel
* LICENSE: This program is free software; you can redistribute it and/or
* modify it under the terms of the GNU General Public License
* as published by the Free Software Foundation; either version 3
* of the License, or (at your option) any later version.
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
* You should have received a copy of the GNU General Public License
* along with this program. If not, see
* http://www.gnu.org/licenses/licenses.html#GPL
*    @author  Oshri Rozmarin <oshri.rozmarin@gmail.com>
* +------------------------------------------------------------------------------+
 *
 */

namespace Multipledb\Model;

use Doctrine\DBAL\Connection;
use OpenEMR\BC\Database;
use OpenEMR\Common\Crypto\CryptoGen;

class MultipledbTable
{
    private function getConnection(): Connection
    {
        return Database::instance()->getConnection();
    }

    public function fetchAll()
    {
        return $this->getConnection()
            ->fetchAllAssociative("SELECT * FROM multiple_db");
    }

    public function checknamespace($namespace)
    {
        $count = (int) $this->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM multiple_db WHERE namespace = ?",
            [$namespace]
        );

        if ($count and $_SESSION['multiple_edit_id'] == 0) {
            return 1;
        } else {
            return 0;
        }
    }

    public function storeMultipledb($id = 0, $db = [])
    {

        if ($db['password']) {
            $cryptoGen = new CryptoGen();
            $db['password'] = $cryptoGen->encryptStandard($db['password']);
        } else {
            unset($db['password']);
        }

        $conn = $this->getConnection();
        if ($id) {
            $conn->update('multiple_db', $db, ['id' => $id]);
        } else {
            $conn->insert('multiple_db', $db);
        }
    }

    public function deleteMultidbById($id)
    {
        $this->getConnection()->delete('multiple_db', ['id' => (int)$id]);
    }

    public function getMultipledbById($id)
    {
        $row = $this->getConnection()->fetchAssociative(
            "SELECT * FROM multiple_db WHERE id = ?",
            [$id]
        );

        return $row ?: false;
    }


    public function randomSafeKey()
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890$%&#@(){}[]<>~=?.*+-!';
        $pass = []; //remember to declare $pass as an array
        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < 32; $i++) {
            $n = mt_rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }

        return implode('', $pass); //turn the array into a string
    }
}
