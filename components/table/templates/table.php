<?php
/**
 * Table Component
 *
 * Data table with column definitions, child component delegation,
 * optional sorting, selection, row actions, and empty state.
 *
 * Props:
 * @var array  $columns        Column definitions (key, label, sortable, align, width, component)
 * @var array  $data           Array of row objects
 * @var string $row_key        Property to use as unique row identifier
 * @var bool   $sortable       Enable sorting globally
 * @var string $sort_by        Currently sorted column key
 * @var string $sort_direction Sort direction: asc|desc
 * @var bool   $selectable     Show selection checkboxes
 * @var array  $selected       Array of selected row keys
 * @var array  $actions        Row action component definitions
 * @var string $empty_icon     SVG icon for empty state
 * @var string $empty_title    Heading when no data
 * @var string $empty_text     Description when no data
 * @var array  $empty_action   Button component for empty state
 */

$columns        = $props['columns'] ?? [];
$data           = $props['data'] ?? [];
$row_key        = $props['row_key'] ?? 'id';
$sortable       = $props['sortable'] ?? false;
$sort_by        = $props['sort_by'] ?? '';
$sort_direction = $props['sort_direction'] ?? 'asc';
$selectable     = $props['selectable'] ?? false;
$selected       = $props['selected'] ?? [];
$actions        = $props['actions'] ?? [];
$empty_icon     = $props['empty_icon'] ?? '';
$empty_title    = $props['empty_title'] ?? 'No items found';
$empty_text     = $props['empty_text'] ?? '';
$empty_action   = $props['empty_action'] ?? [];

$has_actions = !empty($actions);
?>

<div class="anti-table-wrap">
<table class="anti-table">
<?php if (!empty($columns)) : ?>
    <thead class="anti-table__head">
        <tr>
            <?php if ($selectable) : ?>
                <th class="anti-table__th anti-table__th--select" style="width: 40px;">
                    <input type="checkbox" class="anti-table__select-all" aria-label="Select all rows">
                </th>
            <?php endif; ?>

            <?php foreach ($columns as $col) : ?>
                <?php
                    $col_sortable = $sortable && ($col['sortable'] ?? false);
                    $col_align = $col['align'] ?? 'left';
                    $col_width = $col['width'] ?? '';
                    $is_sorted = $col_sortable && ($col['key'] ?? '') === $sort_by;

                    $th_classes = anti_classes([
                        'anti-table__th'              => true,
                        "anti-table__th--{$col_align}" => $col_align !== 'left',
                        'anti-table__th--sortable'    => $col_sortable,
                        'anti-table__th--sorted'      => $is_sorted,
                    ]);

                    $style = !empty($col_width) ? "width: {$col_width};" : '';
                ?>
                <th class="<?php echo attr_escape($th_classes); ?>"<?php echo $style ? ' style="' . attr_escape($style) . '"' : ''; ?>>
                    <?php echo html_escape($col['label'] ?? ''); ?>
                    <?php if ($is_sorted) : ?>
                        <span class="anti-table__sort-icon" aria-label="Sorted <?php echo $sort_direction === 'asc' ? 'ascending' : 'descending'; ?>">
                            <?php echo $sort_direction === 'asc' ? '&#9650;' : '&#9660;'; ?>
                        </span>
                    <?php endif; ?>
                </th>
            <?php endforeach; ?>

            <?php if ($has_actions) : ?>
                <th class="anti-table__th anti-table__th--actions">Actions</th>
            <?php endif; ?>
        </tr>
    </thead>
<?php endif; ?>

    <tbody class="anti-table__body">
        <?php if (empty($data)) : ?>
            <tr class="anti-table__empty-row">
                <td class="anti-table__empty" colspan="<?php echo count($columns) + ($selectable ? 1 : 0) + ($has_actions ? 1 : 0); ?>">
                    <div class="anti-table__empty-content">
                        <?php if (!empty($empty_icon)) : ?>
                            <div class="anti-table__empty-icon"><?php echo $empty_icon; ?></div>
                        <?php endif; ?>
                        <?php if (!empty($empty_title)) : ?>
                            <p class="anti-table__empty-title"><?php echo html_escape($empty_title); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($empty_text)) : ?>
                            <p class="anti-table__empty-text"><?php echo html_escape($empty_text); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($empty_action['name'])) : ?>
                            <div class="anti-table__empty-action">
                                <?php anti_component($empty_action['name'], $empty_action['props'] ?? []); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php else : ?>
            <?php foreach ($data as $row) : ?>
                <?php
                    $row_id = $row[$row_key] ?? '';
                    $is_selected = in_array($row_id, $selected, true);
                ?>
                <tr class="<?php echo anti_classes([
                    'anti-table__row' => true,
                    'anti-table__row--selected' => $is_selected,
                ]); ?>" data-row-key="<?php echo attr_escape((string) $row_id); ?>">
                    <?php if ($selectable) : ?>
                        <td class="anti-table__td anti-table__td--select">
                            <input type="checkbox" <?php echo $is_selected ? 'checked' : ''; ?> aria-label="Select row" value="<?php echo attr_escape((string) $row_id); ?>">
                        </td>
                    <?php endif; ?>

                    <?php foreach ($columns as $col) : ?>
                        <?php
                            $td_align = $col['align'] ?? 'left';
                            $td_classes = anti_classes([
                                'anti-table__td'              => true,
                                "anti-table__td--{$td_align}" => $td_align !== 'left',
                            ]);
                        ?>
                        <td class="<?php echo attr_escape($td_classes); ?>">
                            <?php if (!empty($col['component']['name'])) : ?>
                                <?php
                                    $child_props = $col['component']['props'] ?? [];
                                    $resolved = anti_interpolate_props($child_props, $row);
                                    anti_component($col['component']['name'], $resolved);
                                ?>
                            <?php elseif (isset($col['key'])) : ?>
                                <?php echo html_escape((string) ($row[$col['key']] ?? '')); ?>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>

                    <?php if ($has_actions) : ?>
                        <td class="anti-table__td anti-table__td--actions">
                            <div class="anti-table__actions">
                                <?php foreach ($actions as $action) : ?>
                                    <?php
                                        $action_props = $action['props'] ?? [];
                                        $resolved_action = anti_interpolate_props($action_props, $row);
                                        anti_component($action['name'] ?? 'button', $resolved_action);
                                    ?>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
</div>
