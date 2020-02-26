/**
 * @author Stéphane LaFlèche <stephane.l@vanillaforums.com>
 * @copyright 2009-2019 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

import { StoryHeading } from "@library/storybook/StoryHeading";
import React from "react";
import { StoryContent } from "@library/storybook/StoryContent";
import { color, ColorHelper, percent } from "csx";
import { storiesOf } from "@storybook/react";
import { Form, FormikProvider, useFormik } from "formik";
import ColorPickerBlock from "@library/forms/themeEditor/ColorPickerBlock";
import ThemeBuilderTitle from "@library/forms/themeEditor/ThemeBuilderTitle";
import ThemeBuilderSection from "@library/forms/themeEditor/ThemeBuilderSection";
import ThemeBuilderSectionGroup from "@library/forms/themeEditor/ThemeBuilderSectionGroup";

const story = storiesOf("Theme", module);

story.add("Theme Builder", () => {
    const form = useFormik({
        initialValues: {},
        onSubmit: values => {
            // console.log(values);
        },
    });

    return (
        <StoryContent>
            <StoryHeading depth={1}>Theme Editor</StoryHeading>
            <aside
                style={{
                    width: percent(100),
                    maxWidth: "376px",
                    margin: "auto",
                    backgroundColor: "#f5f6f7",
                    padding: "16px",
                }}
            >
                <FormikProvider value={form}>
                    {/* The translate shouldn't be mandatory, it's a bug in this version of Formik */}
                    <Form translate="yes">
                        <ThemeBuilderTitle />
                        <ColorPickerBlock
                            colorPicker={{
                                variableID: "global.something.or.other.color.1",
                                defaultValue: "#ca0000",
                            }}
                            inputBlock={{ label: "Test 1" }}
                        />
                        <ColorPickerBlock
                            colorPicker={{
                                variableID: "global.something.or.other.color.2",
                                defaultValue: "#00ca25",
                            }}
                            inputBlock={{ label: "Test 2" }}
                        />
                        <ThemeBuilderSection label={"Section 1"}>
                            <ColorPickerBlock
                                colorPicker={{
                                    variableID: "global.something.or.other.color.3",
                                    defaultValue: "#3139ca",
                                }}
                                inputBlock={{ label: "Test 3" }}
                            />
                            <ColorPickerBlock
                                colorPicker={{
                                    variableID: "global.something.or.other.color.4",
                                    defaultValue: "#c627ca",
                                }}
                                inputBlock={{ label: "Test 4" }}
                            />
                        </ThemeBuilderSection>
                        <ThemeBuilderSection label={"Section 31"}>
                            <ColorPickerBlock
                                colorPicker={{
                                    variableID: "global.something.or.other.color.5",
                                    defaultValue: "#c7cac4",
                                }}
                                inputBlock={{ label: "Test 5" }}
                            />
                            <ColorPickerBlock
                                colorPicker={{
                                    variableID: "global.something.or.other.color.6",
                                    defaultValue: "#15206f",
                                }}
                                inputBlock={{ label: "Test 6" }}
                            />
                            <ThemeBuilderSectionGroup label={"Section Sub Group"}>
                                <ColorPickerBlock
                                    colorPicker={{
                                        variableID: "global.something.or.other.color.7",
                                        defaultValue: "cat" as any, // Intentionally bypassing typescript for error
                                    }}
                                    inputBlock={{ label: "With Error" }}
                                />
                                <ColorPickerBlock
                                    colorPicker={{
                                        variableID: "global.something.or.other.color.8",
                                        defaultValue: "chinchilla" as any, // Intentionally bypassing typescript for error
                                    }}
                                    inputBlock={{ label: "With Error" }}
                                />
                                <ColorPickerBlock
                                    colorPicker={{
                                        variableID: "global.something.or.other.color.9",
                                    }}
                                    inputBlock={{ label: "Test 8 - No default value" }}
                                />
                            </ThemeBuilderSectionGroup>
                        </ThemeBuilderSection>
                    </Form>
                </FormikProvider>
            </aside>
        </StoryContent>
    );
});
