<?php

/**
 *
 * MIT License
 *
 * Copyright (C) 2020  Kirill Yegorov https://github.com/k-samuel
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 */
declare(strict_types=1);

namespace KSamuel\FacetedSearch;

use KSamuel\FacetedSearch\Filter\FilterInterface;

/**
 * Class Search
 * Search in faceted index. Easily handles 100,000 products with 10 properties.
 * @package KSamuel\FacetedSearch
 */
class Search
{
    /**
     * @var Index
     */
    protected $index;

    /**
     * Search constructor.
     * @param Index $index
     */
    public function __construct(Index $index)
    {
        $this->index = $index;
    }

    /**
     * Find records by filters as list of int
     * @param array<FilterInterface> $filters
     * @param array<int>|null $inputRecords - list of record id to search in. Use it for limit results
     * @return array<int>
     */
    public function find(array $filters, ?array $inputRecords = null): array
    {
        $input = null;
        if (!empty($inputRecords)) {
            $input = $this->mapInputArray($inputRecords);
        }
        return array_keys($this->findRecordsMap($filters, $input));
    }

    /**
     * @param array<int> $inputRecords
     * @return array<int,bool>
     */
    private function mapInputArray(array $inputRecords): array
    {
        $input = [];
        foreach ($inputRecords as $v) {
            $input[$v] = true;
        }
        return $input;
    }

    /**
     * Find records by filters as array map [$id1=>true, $id2=>true, ...]
     * @param array<FilterInterface> $filters
     * @param array<int,bool>|null $inputRecords
     * @return array<int,bool>
     */
    private function findRecordsMap(array $filters, ?array $inputRecords = null): array
    {
        // if no filters passed
        if (empty($filters)) {
            $total = $this->index->getAllRecordIdMap();
            if (!empty($inputRecords)) {
                return array_intersect_key($total, $inputRecords);
            }
            return $total;
        }

        /**
         * @var array<int,int> $inputRecords
         */
        $result = $inputRecords;

        /**
         * @var FilterInterface $filter
         */
        foreach ($filters as $filter) {
            $indexData = $this->index->getFieldData($filter->getFieldName());
            if (empty($indexData)) {
                return [];
            }
            $result = $filter->filterResults($indexData, $result);
        }

        if (empty($result)) {
            $result = [];
        }
        return $result;
    }

    /**
     * Find acceptable filter values
     * @param array<FilterInterface> $filters
     * @param array<int> $inputRecords
     * @return array<string,array<int|string,int|string>>
     */
    private function findFilters(array $filters = [], array $inputRecords = [], bool $countValues = false): array
    {
        $input = null;
        if (!empty($inputRecords)) {
            $input = $this->mapInputArray($inputRecords);
        }

        $result = [];
        $facetsData = $this->index->getData();
        $indexedFilters = [];
        $filteredRecords = [];

        if (!empty($filters)) {
            // index filters by field
            foreach ($filters as $filter) {
                /**
                 * @var FilterInterface $filter
                 */
                $indexedFilters[$filter->getFieldName()] = $filter;
            }
            $filteredRecords = $this->findRecordsMap($indexedFilters, $input);
        }

        foreach ($facetsData as $filterName => $filterValues) {
            /**
             * @var string $filterName
             */
            if (empty($indexedFilters) && empty($input)) {
                if ($countValues) {
                    // need to count values
                    foreach ($filterValues as $key => $list) {
                        $result[$filterName][$key] = count($list);
                    }
                } else {
                    $result[$filterName] = array_keys($filterValues);
                }
                continue;
            }

            $filtersCopy = $indexedFilters;
            // do not apply self filtering
            if (isset($filtersCopy[$filterName])) {
                unset($filtersCopy[$filterName]);
                $recordIds = $this->findRecordsMap($filtersCopy, $input);
            } else {
                $recordIds = $filteredRecords;
            }

            foreach ($filterValues as $filterValue => $data) {

                $intersect = array_intersect_key($data, $recordIds);

                if (empty($intersect)) {
                    continue;
                }

                if ($countValues) {
                    // need to count values
                    $result[$filterName][$filterValue] = count($intersect);
                } else {
                    // results without count
                    $result[$filterName][] = $filterValue;
                }
            }
        }
        return $result;
    }

    /**
     * Find acceptable filter values
     * @param array<FilterInterface> $filters
     * @param array<int> $inputRecords
     * @return array<string,array<int|string,int|string>>
     */
    public function findAcceptableFilters(array $filters = [], array $inputRecords = []): array
    {
        return $this->findFilters($filters, $inputRecords, false);
    }

    /**
     * Find acceptable filters with values count
     * @param array<FilterInterface> $filters
     * @param array<int> $inputRecords
     * @return array<string,array<int|string,int|string>>
     */
    public function findAcceptableFiltersCount(array $filters = [], array $inputRecords = []): array
    {
        return $this->findFilters($filters, $inputRecords, true);
    }
}