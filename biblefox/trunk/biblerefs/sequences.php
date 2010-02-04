<?php

class BfoxSequence {
	public $start, $end;

	public function __construct($start = 0, $end = 0) {
		$this->start = $start;
		$this->end = $end;
	}
}

abstract class BfoxSequenceList {
	protected $sequences = array();

	public function is_valid() {
		return (!empty($this->sequences));
	}

	/**
	 * Return the sequences
	 *
	 * @return array of objects
	 */
	public function get_seqs() {
		return $this->sequences;
	}

	protected function add_seqs($seqs) {
		foreach ($seqs as $seq) $this->add_seq($seq);
	}

	protected function sub_seqs($seqs) {
		foreach ($seqs as $seq) $this->sub_seq($seq);
	}

	public function start() {
		if (!empty($this->sequences)) return $this->sequences[0]->start;
		return 0;
	}

	public function end() {
		$index = count($this->sequences) - 1;
		if (0 <= $index) return $this->sequences[$index]->end;
		return 0;
	}

	/**
	 * Adds a new sequence to the sequence list
	 *
	 * This function maintains that there are no overlapping sequences and that they are in order from lowest to highest
	 *
	 * @param integer $start
	 * @param integer $end
	 */
	public function add_seq(BfoxSequence $seq) {
		// Make a copy of the sequence since it was passed by reference
		$new_seq = new BfoxSequence($seq->start, $seq->end);

		$new_seqs = array();
		foreach ($this->sequences as $seq) {
			if (isset($new_seq)) {
				// If the new seq starts before seq
				if ($new_seq->start < $seq->start) {
					// If the new seq also ends before, then we've found the spot to place it
					// Otherwise, it intersects, so modify the new seq to include seq
					if (($new_seq->end + 1) < $seq->start) {
						$new_seqs []= $new_seq;
						$new_seqs []= $seq;
						unset($new_seq);
					}
					else {
						if ($new_seq->end < $seq->end) $new_seq->end = $seq->end;
					}
				}
				else {
					// The new seq starts with or after seq
					// If the new seq starts before seq ends, we have an intersection
					// Otherwise, we passed seq without intersecting it, so add it to the array
					if (($new_seq->start - 1) <= $seq->end) {
						$new_seq->start = $seq->start;
						if ($new_seq->end < $seq->end) $new_seq->end = $seq->end;
					}
					else {
						$new_seqs []= $seq;
					}
				}
			}
			else $new_seqs []= $seq;
		}
		if (isset($new_seq)) $new_seqs []= $new_seq;

		$this->sequences = $new_seqs;
	}

	/**
	 * Subtracts a sequence from the list
	 *
	 * @param integer $start
	 * @param integer $end
	 */
	public function sub_seq(BfoxSequence $seq) {
		// Make a copy of the sequence since it was passed by reference
		$sub_seq = new BfoxSequence($seq->start, $seq->end);

		$new_seqs = array();
		foreach ($this->sequences as $seq) {
			if (isset($sub_seq)) {
				// If the seq starts before sub_seq
				if ($seq->start < $sub_seq->start) {
					// If the seq also ends before sub seq, then it is fine
					if ($seq->end < $sub_seq->start) {
						$new_seqs []= $seq;
					}
					// Otherwise, if the seq ends before sub_seq ends, we need to adjust the end
					elseif ($seq->end <= $sub_seq->end) {
						$seq->end = $sub_seq->start - 1;
						$new_seqs []= $seq;
					}
					// Otherwise, the seq ends after sub_seq ends, so we need to split the seq
					else {
						// Create a new seq that starts after the sub_seq
						$new_seq->start = $sub_seq->end + 1;
						$new_seq->end = $seq->end;

						// Adjust the old seq to end before the sub_seq
						$seq->end = $sub_seq->start - 1;

						// Add the seqs
						$new_seqs []= $seq;
						$new_seqs []= $new_seq;
					}
				}
				else {
					// The seq starts between or after sub_seq
					// If the seq starts between...
					// Otherwise, the seq starts after and is fine
					if ($seq->start <= $sub_seq->end) {
						// If the seq ends after the sub_seq, then we can add the last portion
						if ($seq->end > $sub_seq->end) {
							$seq->start = $sub_seq->end + 1;
							$new_seqs []= $seq;
						}
					}
					else {
						$new_seqs []= $seq;
						// We've passed the sub_seq, so we don't need it anymore
						unset($sub_seq);
					}
				}
			}
			else $new_seqs []= $seq;
		}

		$this->sequences = $new_seqs;
	}

	/**
	 * Returns an SQL expression for comparing these bible references against one unique id column
	 *
	 * @param string $col1
	 * @return string
	 */
	public function sql_where($col1 = 'unique_id') {
		global $wpdb;

		$wheres = array();
		foreach ($this->sequences as $seq) $wheres []= $wpdb->prepare("($col1 >= %d AND $col1 <= %d)", $seq->start, $seq->end);

		return '(' . implode(' OR ', $wheres) . ')';
	}

	/**
	 * Returns an SQL expression for comparing these bible references against two unique id columns
	 *
	 * @param string $col1
	 * @param string $col2
	 * @return string
	 */
	public function sql_where2($col1 = 'start', $col2 = 'end') {
		/*
		 Equation for determining whether one bible reference overlaps another

		 a1 <= b1 and b1 <= a2 or
		 a1 <= b2 and b2 <= a2
		 or
		 b1 <= a1 and a1 <= b2 or
		 b1 <= a2 and a2 <= b2

		 a1b1 * b1a2 + a1b2 * b2a2 + b1a1 * a1b2 + b1a2 * a2b2
		 b1a2 * (a1b1 + a2b2) + a1b2 * (b1a1 + b2a2)

		 */

		global $wpdb;

		$wheres = array();

		// Old equations using reduced <= and >= operators - these might not be as easy for MySQL to optimize as the BETWEEN operator
		/*foreach ($this->sequences as $seq) $wheres []= $wpdb->prepare(
			"((($col1 <= %d) AND ((%d <= $col1) OR (%d <= $col2))) OR
			((%d <= $col2) AND (($col1 <= %d) OR ($col2 <= %d))))",
			$seq->end, $seq->start, $seq->end,
			$seq->start, $seq->start, $seq->end);*/

		// Using the BETWEEN operator
		foreach ($this->sequences as $seq) $wheres []= $wpdb->prepare(
			"($col1 BETWEEN %d AND %d) OR ($col2 BETWEEN %d AND %d) OR (%d BETWEEN $col1 AND $col2) OR (%d BETWEEN $col1 AND $col2)",
			$seq->start, $seq->end, $seq->start, $seq->end, $seq->start, $seq->end);

		if (!empty($wheres)) return '(' . implode(' OR ', $wheres) . ')';
		return "0";
	}
}

?>