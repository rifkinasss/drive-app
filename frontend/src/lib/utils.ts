export function cn(
  ...values: Array<string | false | null | undefined>
): string {
  return values.filter(Boolean).join(" ");
}
export function getUniqueFilename(
  filename: string,
  existingNames: string[],
): string {
  const names = new Set(existingNames);
  if (!names.has(filename)) return filename;
  const match = filename.match(/^(.*?)(\.[^.]+)?$/);
  const base = match?.[1] ?? filename;
  const extension = match?.[2] ?? "";
  let index = 1;
  while (names.has(`${base} (${index})${extension}`)) index += 1;
  return `${base} (${index})${extension}`;
}
