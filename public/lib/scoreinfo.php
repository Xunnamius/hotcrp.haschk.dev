<?php
// scoreinfo.php -- HotCRP score analysis helper.
// Copyright (c) 2006-2018 Eddie Kohler; see LICENSE.

class ScoreInfo {
    private $_scores = array();
    private $_keyed = true;
    private $_sorted = false;
    private $_sum = 0;
    private $_sumsq = 0;
    private $_n = 0;
    private $_positive;

    const COUNT = 0;
    const MEAN = 1;
    const MEDIAN = 2;
    const SUM = 3;
    const VARIANCE_P = 4;
    const STDDEV_P = 5;

    static public $stat_names = ["Count", "Mean", "Median", "Total", "Variance", "Standard deviation"];
    static public $stat_keys = ["count", "mean", "median", "total", "var_p", "stddev_p"];

    function __construct($data = null, $positive = false) {
        $this->_positive = $positive;
        if (is_array($data)) {
            foreach ($data as $key => $x)
                $this->add($x, $key);
        } else if (is_string($data) && $data !== "") {
            foreach (preg_split('/[\s,]+/', $data) as $x)
                if (is_numeric($x))
                    $this->add(+$x);
        }
    }

    static function mean_of($data, $positive = false) {
        $n = $sum = 0;
        if (is_array($data)) {
            foreach ($data as $x)
                if ($x !== null && (!$positive || $x > 0)) {
                    ++$n;
                    $sum += +$x;
                }
        } else if (is_string($data) && $data !== "") {
            foreach (preg_split('/[\s,]+/', $data) as $x)
                if ($x !== "" && is_numeric($x)) {
                    $x = +$x;
                    if (!$positive || $x > 0) {
                        ++$n;
                        $sum += +$x;
                    }
                }
        }
        return $n ? $sum / $n : null;
    }

    function add($x, $key = null) {
        if (is_bool($x))
            $x = +$x;
        if ($x !== null && (!$this->_positive || $x > 0)) {
            if ($this->_keyed && $key === null)
                $this->_keyed = false;
            if ($this->_keyed)
                $this->_scores[$key] = $x;
            else
                $this->_scores[] = $x;
            $this->_sum += $x;
            $this->_sumsq += $x * $x;
            ++$this->_n;
            $this->_sorted = false;
        }
    }

    function count() {
        return $this->_n;
    }

    function n() {
        return $this->_n;
    }

    function mean() {
        return $this->_n > 0 ? $this->_sum / $this->_n : 0;
    }

    function variance_s() {
        return $this->_n > 1 ? ($this->_sumsq - $this->_sum * $this->_sum / $this->_n) / ($this->_n - 1) : 0;
    }

    function stddev_s() {
        return sqrt($this->variance_s());
    }

    function variance_p() {
        return $this->_n > 1 ? ($this->_sumsq - $this->_sum * $this->_sum / $this->_n) / $this->_n : 0;
    }

    function stddev_p() {
        return sqrt($this->variance_p());
    }

    function counts($max = 0) {
        $counts = $max ? array_fill(0, $max, 0) : array();
        foreach ($this->_scores as $i) {
            while ($i > count($counts))
                $counts[] = 0;
            if ($i > 0)
                ++$counts[$i - 1];
        }
        return $counts;
    }

    private function sort() {
        if (!$this->_sorted) {
            $this->_keyed ? asort($this->_scores) : sort($this->_scores);
            $this->_sorted = true;
        }
    }

    function median() {
        $this->sort();
        $a = $this->_keyed ? array_values($this->_scores) : $this->_scores;
        if ($this->_n % 2)
            return $a[($this->_n - 1) >> 1];
        else if ($this->_n)
            return ($a[($this->_n - 2) >> 1] + $a[$this->_n >> 1]) / 2;
        else
            return 0;
    }

    function max() {
        return empty($this->_scores) ? 0 : max($this->_scores);
    }

    function min() {
        return empty($this->_scores) ? 0 : min($this->_scores);
    }

    function statistic($stat) {
        if ($stat == self::COUNT)
            return $this->_n;
        else if ($stat == self::MEAN)
            return $this->mean();
        else if ($stat == self::MEDIAN)
            return $this->median();
        else if ($stat == self::SUM)
            return $this->_sum;
        else if ($stat == self::VARIANCE_P)
            return $this->variance_p();
        else if ($stat == self::STDDEV_P)
            return $this->stddev_p();
    }

    function sort_data($sorter, $key = null) {
        if ($sorter == "Y" && $key !== null && $this->_keyed)
            return get($this->_scores, $key, -1000000);
        else if ($sorter == "Y" || $sorter == "C" || $sorter == "M") {
            $this->sort();
            return $this->_scores ? array_values($this->_scores) : null;
        } else if ($sorter == "E")
            return $this->median();
        else if ($sorter == "V")
            return $this->variance_p();
        else if ($sorter == "D")
            return $this->max() - $this->min();
        else
            return $this->mean();
    }

    static function compare($av, $bv, $null_direction = 1) {
        if ($av === null || $bv === null)
            return $av !== null ? -$null_direction : ($bv !== null ? $null_direction : 0);
        if (is_array($av)) {
            $ap = count($av);
            $bp = count($bv);
            $ax = $bx = -999999;
            do {
                --$ap;
                if ($ap >= 0)
                    $ax = $av[$ap];
                else if ($ap == -1)
                    --$ax;
                --$bp;
                if ($bp >= 0)
                    $bx = $bv[$bp];
                else if ($bp == -1)
                    --$bx;
                if ($ax != $bx)
                    return $ax < $bx ? -1 : 1;
            } while ($ap >= 0 || $bp >= 0);
        } else if ($av != $bv)
            return $av < $bv ? -1 : 1;
        return 0;
    }

    function compare_by(ScoreInfo $b, $sorter, $key = null) {
        return self::compare($this->sort_data($sorter, $key),
                             $b->sort_data($sorter, $key));
    }
}
