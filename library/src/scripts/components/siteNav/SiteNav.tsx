/*
 * @author Stéphane LaFlèche <stephane.l@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

import { INavigationTreeItem } from "@library/@types/api";
import { getRequiredID } from "@library/componentIDs";
import SiteNavNode, { IActiveRecord } from "@library/components/siteNav/SiteNavNode";
import TabHandler from "@library/TabHandler";
import classNames from "classnames";
import * as React from "react";
import { RouteComponentProps, withRouter } from "react-router-dom";
import { t } from "@library/application";

interface IProps extends RouteComponentProps<{}> {
    activeRecord: IActiveRecord;
    id?: string;
    className?: string;
    children: INavigationTreeItem[];
    collapsible: boolean;
}

export interface IState {
    id: string;
}

/**
 * Implementation of SiteNav component
 */
export class SiteNav extends React.Component<IProps, IState> {
    public render() {
        const content =
            this.props.children && this.props.children.length > 0
                ? this.props.children.map((child, i) => {
                      return (
                          <SiteNavNode
                              {...child}
                              activeRecord={this.props.activeRecord}
                              key={`navNode-${i}`}
                              counter={i}
                              titleID={this.titleID}
                              depth={0}
                              collapsible={this.props.collapsible}
                          />
                      );
                  })
                : null;
        return (
            <nav onKeyDownCapture={this.handleKeyDown} className={classNames("siteNav", this.props.className)}>
                <h2 id={this.titleID} className="sr-only">
                    {t("Site Navigation")}
                </h2>
                <ul className="siteNav-children hasDepth-0" role="tree" aria-labelledby={this.titleID}>
                    {content}
                </ul>
            </nav>
        );
    }

    private id = getRequiredID(this.props, "siteNav");

    public get titleID() {
        return this.id + "-title";
    }

    /**
     * Get first element of type in container, excluding hidden elements
     * @param container The container we're looking in
     * @param selector The selector to find element
     */
    public firstVisibleOfType = (container: Element, selector: string = ".siteNavNode") => {
        return container.querySelector(selector + ":not(.isHidden):first-child") || null;
    };

    /**
     * Get last element of type in container, excluding hidden elements
     * @param container The container we're looking in
     * @param selector The selector to find element
     */
    public lastVisibleOfType = (container: Element, selector: string = ".siteNavNode") => {
        return container.querySelector(selector + ":not(.isHidden):last-child") || null;
    };

    /**
     * Keyboard handler for arrow up, arrow down, home and end.
     * For full accessibility docs, see https://www.w3.org/TR/wai-aria-practices-1.1/examples/treeview/treeview-1/treeview-1a.html
     * Note that some of the events are on SiteNavNode.tsx
     * @param event
     */
    private handleKeyDown = (event: React.KeyboardEvent) => {
        if (document.activeElement === null) {
            return;
        }
        const currentLink = document.activeElement;
        const selectedNode = currentLink.closest(".siteNavNode");
        const siteNavRoot = currentLink.closest(".siteNav");
        const tabHandler = new TabHandler(siteNavRoot!);

        switch (
            event.key // See SiteNavNode for the rest of the keyboard handler
        ) {
            case "ArrowDown":
                /*
                    Moves focus one row or one cell down, depending on whether a row or cell is currently focused.
                    If focus is on the bottom row, focus does not move.
                 */
                if (siteNavRoot) {
                    event.preventDefault();
                    event.stopPropagation();
                    if (selectedNode && currentLink) {
                        const nextElement = tabHandler.getNext(currentLink, false, false);
                        if (nextElement) {
                            nextElement.focus();
                        }
                    }
                }
                break;
            case "ArrowUp":
                /*
                    Moves focus one row or one cell up, depending on whether a row or cell is currently focused.
                    If focus is on the top row, focus does not move.
                 */
                if (selectedNode && currentLink) {
                    event.preventDefault();
                    event.stopPropagation();
                    const prevElement = tabHandler.getNext(currentLink, true, false);
                    if (prevElement) {
                        prevElement.focus();
                    }
                }
                break;
            case "Home":
                /*
                    If a cell is focused, moves focus to the previous interactive widget in the current row.
                    If a row is focused, moves focus out of the treegrid.
                 */
                event.preventDefault();
                event.stopPropagation();
                const firstLink = tabHandler.getInitial();
                if (firstLink) {
                    firstLink.focus();
                }
                break;
            case "End":
                /*
                    If a row is focused, moves to the first row.
                    If a cell is focused, moves focus to the first cell in the row containing focus.
                 */
                event.preventDefault();
                event.stopPropagation();
                const lastLink = tabHandler.getLast();
                if (lastLink) {
                    lastLink.focus();
                }
                break;
        }
    };
}

export default withRouter<IProps>(SiteNav);
