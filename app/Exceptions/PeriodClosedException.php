<?php

namespace App\Exceptions;

/** Transaksi menyentuh periode yang sudah Tutup Buku. Turunan DomainException agar controller yang sudah menangkapnya menampilkan pesan. */
class PeriodClosedException extends \DomainException
{
}
