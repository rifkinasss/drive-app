export const appConfig = {
  name: "Drive by NasLabs",
  shortName: "Drive",
  version: process.env.NEXT_PUBLIC_APP_VERSION ?? "2.0.0",
  description: "Private file storage and sharing by NasLabs",
} as const;
