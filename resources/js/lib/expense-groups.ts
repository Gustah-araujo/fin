interface CategoryWithGroup {
    name: string;
    expense_group?: { uuid: string; name: string; color: string } | null;
}

interface GroupDisplay {
    value: string;
    hint: string | null;
}

export function resolveGroupDisplay(
    selected: string,
    category: CategoryWithGroup | undefined,
): GroupDisplay {
    if (selected !== '') {
        return { value: selected, hint: null };
    }

    if (category?.expense_group) {
        return {
            value: category.expense_group.uuid,
            hint: `Herdado da categoria: ${category.name}`,
        };
    }

    return { value: '', hint: null };
}
